<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\MessageSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Envía un mensaje respetando el límite por minuto del proveedor. Reintenta errores temporales
 * (hasta 3 intentos con espera creciente) y registra el resultado final.
 */
class SendWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public const MAX_SEND_ATTEMPTS = 3;

    public int $tries = 0; // controlado por retryUntil (los bloqueos por límite de tasa no cuentan como fallos)

    public int $timeout = 60;

    public function __construct(public int $messageId) {}

    public function middleware(): array
    {
        return [new RateLimited('whatsapp-outbound')];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(MessageSender $sender, CampaignService $campaigns): void
    {
        $message = Message::find($this->messageId);
        if (! $message || ! in_array($message->status, [MessageStatus::Pending, MessageStatus::Queued], true)) {
            return;
        }

        $sender->deliver($message);
        $message->refresh();

        // Error temporal: reintentar con espera creciente
        if (in_array($message->status, [MessageStatus::Pending, MessageStatus::Queued], true)) {
            if ($message->attempts < self::MAX_SEND_ATTEMPTS) {
                $this->release([30, 120, 600][$message->attempts - 1] ?? 600);

                return;
            }
            $sender->markFailed($message, 'Se agotaron los reintentos de envío.');
        }

        if ($message->campaign_id) {
            $campaigns->recordResult($message);
        }
    }

    public function failed(?Throwable $e): void
    {
        $message = Message::find($this->messageId);
        if ($message && in_array($message->status, [MessageStatus::Pending, MessageStatus::Queued], true)) {
            app(MessageSender::class)->markFailed($message, 'Error al procesar el envío: '.$e?->getMessage());
            if ($message->campaign_id) {
                app(CampaignService::class)->recordResult($message->fresh());
            }
        }
    }
}
