<?php

namespace App\Services\Conversations;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Services\Messaging\CampaignService;
use App\Services\WhatsApp\Data\StatusUpdate;

/**
 * Aplica los estados de entrega y lectura informados por el proveedor (webhooks de estado).
 * Los estados solo avanzan (enviado → entregado → leído); un fallo se registra con su error.
 */
class StatusUpdateService
{
    private const ORDER = ['pending' => 0, 'queued' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    public function __construct(private CampaignService $campaigns) {}

    public function apply(StatusUpdate $update): ?Message
    {
        $message = Message::where('provider_message_id', $update->providerMessageId)->first();
        if (! $message) {
            return null;
        }

        $at = $update->occurredAt ?? now();

        if ($update->status === MessageStatus::Failed) {
            $message->forceFill([
                'status' => MessageStatus::Failed,
                'failed_at' => $at,
                'error_code' => $update->errorCode,
                'error_message' => $update->errorMessage ? mb_substr($update->errorMessage, 0, 490) : 'Entrega fallida reportada por el proveedor',
            ])->save();
        } else {
            $current = self::ORDER[$message->status->value] ?? 0;
            $new = self::ORDER[$update->status->value] ?? 0;
            if ($new <= $current) {
                return $message;
            }
            $message->forceFill(array_filter([
                'status' => $update->status,
                'sent_at' => $message->sent_at ?? $at,
                'delivered_at' => in_array($update->status, [MessageStatus::Delivered, MessageStatus::Read], true) ? ($message->delivered_at ?? $at) : null,
                'read_at' => $update->status === MessageStatus::Read ? $at : null,
            ]))->save();
        }

        if ($message->campaign_id) {
            $this->campaigns->recordResult($message);
        }

        return $message;
    }
}
