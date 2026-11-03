<?php

namespace App\Services\Messaging;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\RecipientStatus;
use App\Enums\SenderType;
use App\Jobs\ProcessCampaignJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\DelinquencyStatus;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Notifications\SystemNotification;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de una campaña: preparar destinatarios, lanzar, procesar envíos, registrar resultados y finalizar.
 */
class CampaignService
{
    public function __construct(
        private CampaignAudience $audience,
        private TemplateRenderer $renderer,
        private MessageSender $sender,
    ) {}

    /** Lanza una campaña ahora o la deja programada. */
    public function launch(Campaign $campaign): void
    {
        if (! $campaign->isEditable()) {
            throw new \RuntimeException('La campaña ya fue lanzada o cancelada.');
        }

        if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
            $campaign->update(['status' => CampaignStatus::Scheduled]);
            ActivityLogger::log('campaign.scheduled', "Programó la campaña “{$campaign->name}” para {$campaign->scheduled_at->format('d/m/Y h:i a')}", $campaign);

            return;
        }

        $this->start($campaign);
    }

    /** Toma una "foto" de la audiencia elegible, crea los destinatarios y encola el procesamiento. */
    public function start(Campaign $campaign): void
    {
        $updated = Campaign::whereKey($campaign->id)
            ->whereIn('status', [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value])
            ->update(['status' => CampaignStatus::Processing->value, 'started_at' => now()]);

        if ($updated === 0) {
            return; // ya iniciada por otro proceso
        }

        $campaign->refresh();
        $this->prepareRecipients($campaign);

        ActivityLogger::log('campaign.started', "Inició la campaña “{$campaign->name}” con {$campaign->total_recipients} destinatarios", $campaign);
        ProcessCampaignJob::dispatch($campaign->id)->onQueue('campaigns');
    }

    public function prepareRecipients(Campaign $campaign): void
    {
        $campaign->loadMissing('template');
        // Mapa id de estado de mora => plantilla activa asociada (sin flatMap: reindexaría las claves numéricas).
        $statusTemplates = collect();
        if ($campaign->use_status_templates) {
            $templates = MessageTemplate::query()->active()->get()->keyBy('id');
            foreach (DelinquencyStatus::query()->whereNotNull('message_template_id')->get(['id', 'message_template_id']) as $status) {
                if ($templates->has($status->message_template_id)) {
                    $statusTemplates->put($status->id, $templates->get($status->message_template_id));
                }
            }
        }

        $this->audience->eligible($campaign->type, $campaign->filters ?? [])
            ->with(['articles', 'delinquencyStatus'])
            ->chunkById(200, function ($customers) use ($campaign, $statusTemplates) {
                $rows = [];
                foreach ($customers as $customer) {
                    $template = $campaign->use_status_templates ? $statusTemplates->get($customer->delinquency_status_id) : $campaign->template;
                    $rows[] = [
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'phone' => $customer->whatsapp_number,
                        'message_template_id' => $template?->id,
                        'rendered_body' => $template ? $this->renderer->render($template->body, $customer) : null,
                        'status' => $template ? RecipientStatus::Pending->value : RecipientStatus::Skipped->value,
                        'skip_reason' => $template ? null : 'El estado de mora del cliente no tiene plantilla asociada',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                // La llave única (campaign_id, customer_id) impide destinatarios duplicados.
                CampaignRecipient::insertOrIgnore($rows);
            }, 'customers.id', 'id');

        $this->refreshCounters($campaign);
    }

    /** Crea los mensajes de los destinatarios pendientes y los encola respetando las reglas anti-duplicado. */
    public function dispatchPending(Campaign $campaign): void
    {
        $minHours = (int) setting('communication.min_hours_between_collection_messages', 24);

        $campaign->recipients()
            ->where('status', RecipientStatus::Pending)
            ->with('customer:id,whatsapp_number,whatsapp_opted_out_at,status,accepts_promotions')
            ->chunkById(200, function ($recipients) use ($campaign, $minHours) {
                foreach ($recipients as $recipient) {
                    $customer = $recipient->customer;

                    if (! $customer || ! $customer->isActive() || $customer->whatsapp_opted_out_at) {
                        $this->skip($recipient, 'El cliente fue desactivado o solicitó la baja antes del envío');

                        continue;
                    }
                    if ($campaign->type === CampaignType::Promotion && ! $customer->accepts_promotions) {
                        $this->skip($recipient, 'El cliente retiró su consentimiento para promociones');

                        continue;
                    }
                    if ($campaign->type === CampaignType::Collection && $minHours > 0 && $this->recentlyContacted($customer->id, $campaign->id, $minHours)) {
                        $this->skip($recipient, "Ya recibió un mensaje de cobranza en las últimas {$minHours} horas");

                        continue;
                    }

                    // Se crea el mensaje, se vincula al destinatario y solo entonces se encola (evita carreras con el worker).
                    $message = $this->sender->create($recipient->phone, (string) $recipient->rendered_body, SenderType::Campaign, [
                        'customer_id' => $customer->id,
                        'campaign_id' => $campaign->id,
                        'message_template_id' => $recipient->message_template_id,
                    ], "campaign:{$campaign->id}:customer:{$customer->id}");

                    $recipient->update(['status' => RecipientStatus::Queued, 'message_id' => $message->id]);

                    if ($message->wasRecentlyCreated) {
                        $this->sender->enqueue($message);
                    }
                }
            });

        $this->refreshCounters($campaign);
        $this->completeIfDone($campaign);
    }

    /** Registra el resultado de un mensaje de campaña (lo llama el job de envío). */
    public function recordResult(Message $message): void
    {
        $status = match ($message->status) {
            MessageStatus::Failed => RecipientStatus::Failed,
            MessageStatus::Sent, MessageStatus::Delivered, MessageStatus::Read => RecipientStatus::Sent,
            default => null,
        };
        if ($status === null) {
            return;
        }

        CampaignRecipient::where('message_id', $message->id)->update([
            'status' => $status,
            'error_message' => $status === RecipientStatus::Failed ? $message->error_message : null,
            'processed_at' => now(),
        ]);

        $campaign = Campaign::find($message->campaign_id);
        if ($campaign) {
            $this->refreshCounters($campaign);
            $this->completeIfDone($campaign);
        }
    }

    public function refreshCounters(Campaign $campaign): void
    {
        $counts = $campaign->recipients()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $campaign->forceFill([
            'total_recipients' => (int) $counts->sum(),
            'sent_count' => (int) ($counts[RecipientStatus::Sent->value] ?? 0),
            'failed_count' => (int) ($counts[RecipientStatus::Failed->value] ?? 0),
            'skipped_count' => (int) ($counts[RecipientStatus::Skipped->value] ?? 0),
        ])->save();
    }

    public function completeIfDone(Campaign $campaign): void
    {
        $open = $campaign->recipients()->whereIn('status', [RecipientStatus::Pending, RecipientStatus::Queued])->exists();
        if ($open) {
            return;
        }

        $done = Campaign::whereKey($campaign->id)->where('status', CampaignStatus::Processing)
            ->update(['status' => CampaignStatus::Completed->value, 'completed_at' => now()]);

        if ($done === 0) {
            return;
        }

        $campaign->refresh();
        ActivityLogger::log('campaign.completed', "Finalizó la campaña “{$campaign->name}”: {$campaign->sent_count} enviados, {$campaign->failed_count} fallidos", $campaign, [], $campaign->created_by);

        $notifications = app(NotificationService::class);
        $route = $campaign->type === CampaignType::Promotion ? 'promotions.show' : 'campaigns.show';
        $notifications->toUser($campaign->creator()->first(), new SystemNotification(
            'campaign_finished', 'Campaña finalizada',
            "“{$campaign->name}”: {$campaign->sent_count} enviados, {$campaign->failed_count} fallidos, {$campaign->skipped_count} omitidos.",
            'megaphone', 'green', route($route, $campaign),
        ));

        if ($campaign->failed_count > 0) {
            $notifications->toPermission('campaigns.manage', new SystemNotification(
                'messages_failed', 'Mensajes fallidos en campaña',
                "La campaña “{$campaign->name}” tuvo {$campaign->failed_count} mensajes fallidos.",
                'circle-x', 'red', route($route, $campaign),
            ));
        }
    }

    public function cancel(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign->recipients()->where('status', RecipientStatus::Pending)
                ->update(['status' => RecipientStatus::Skipped, 'skip_reason' => 'Campaña cancelada']);
            $campaign->update(['status' => CampaignStatus::Cancelled, 'completed_at' => now()]);
        });
        $this->refreshCounters($campaign);
        ActivityLogger::log('campaign.cancelled', "Canceló la campaña “{$campaign->name}”", $campaign);
    }

    /** Reintenta los envíos fallidos de una campaña finalizada. */
    public function retryFailed(Campaign $campaign): int
    {
        $failed = $campaign->recipients()->where('status', RecipientStatus::Failed)->with('message')->get();

        foreach ($failed as $recipient) {
            if ($recipient->message) {
                $recipient->message->forceFill(['status' => MessageStatus::Queued, 'error_code' => null, 'error_message' => null, 'failed_at' => null, 'attempts' => 0])->save();
                SendWhatsAppMessageJob::dispatch($recipient->message->id)->onQueue('whatsapp');
            }
            $recipient->update(['status' => RecipientStatus::Queued, 'error_message' => null]);
        }

        if ($failed->isNotEmpty()) {
            $campaign->update(['status' => CampaignStatus::Processing, 'completed_at' => null]);
            $this->refreshCounters($campaign);
            ActivityLogger::log('campaign.retry', "Reintentó {$failed->count()} envíos fallidos de “{$campaign->name}”", $campaign);
        }

        return $failed->count();
    }

    private function skip(CampaignRecipient $recipient, string $reason): void
    {
        $recipient->update(['status' => RecipientStatus::Skipped, 'skip_reason' => $reason, 'processed_at' => now()]);
    }

    private function recentlyContacted(int $customerId, int $campaignId, int $hours): bool
    {
        return Message::query()
            ->where('customer_id', $customerId)
            ->where('direction', MessageDirection::Outbound)
            ->where('sender_type', SenderType::Campaign)
            ->where('campaign_id', '!=', $campaignId)
            ->whereIn('status', [MessageStatus::Queued->value, MessageStatus::Sent->value, MessageStatus::Delivered->value, MessageStatus::Read->value])
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereHas('campaign', fn ($q) => $q->where('type', CampaignType::Collection))
            ->exists();
    }
}
