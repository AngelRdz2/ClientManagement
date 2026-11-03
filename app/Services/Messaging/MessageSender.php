<?php

namespace App\Services\Messaging;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Services\WhatsApp\Data\SendResult;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Punto único de salida de mensajes de WhatsApp. Registra cada mensaje, evita duplicados mediante
 * claves de idempotencia y delega el envío al proveedor activo.
 */
class MessageSender
{
    public function __construct(
        private WhatsAppManager $whatsapp,
        private TemplateRenderer $renderer,
    ) {}

    /**
     * Crea el mensaje y lo encola. Si ya existe un mensaje con la misma clave de idempotencia, lo devuelve sin duplicarlo.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function queue(string $phone, string $body, SenderType $sender, array $attributes = [], ?string $idempotencyKey = null): Message
    {
        $message = $this->create($phone, $body, $sender, $attributes, $idempotencyKey);

        if ($message->wasRecentlyCreated) {
            $this->enqueue($message);
        }

        return $message;
    }

    public function enqueue(Message $message): void
    {
        $message->update(['status' => MessageStatus::Queued]);
        SendWhatsAppMessageJob::dispatch($message->id)->onQueue('whatsapp');
    }

    /**
     * Envío inmediato (respuestas del bot y de asesores dentro de una conversación).
     * Registra además la entrada en la línea de tiempo de la conversación.
     */
    public function replyInConversation(Conversation $conversation, string $body, SenderType $sender, ?User $user = null, array $attributes = []): Message
    {
        $message = $this->create($conversation->phone, $body, $sender, array_merge([
            'customer_id' => $conversation->customer_id,
            'conversation_id' => $conversation->id,
            'user_id' => $user?->id,
            // Las conversaciones del simulador nunca se envían por un proveedor real.
            'provider' => $conversation->is_simulated ? 'simulator' : $this->whatsapp->activeName(),
        ], $attributes));

        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'type' => 'message',
            'sender_type' => $sender,
            'user_id' => $user?->id,
            'body' => $body,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($body, 120),
        ])->save();

        return $this->deliver($message);
    }

    /** Envía un mensaje directo a un cliente fuera de campañas (p. ej. desde su ficha). */
    public function sendToCustomer(Customer $customer, string $body, ?User $user = null): Message
    {
        $message = $this->create((string) $customer->whatsapp_number, $body, SenderType::Agent, [
            'customer_id' => $customer->id,
            'user_id' => $user?->id,
        ]);

        return $this->deliver($message);
    }

    /** @param array<string, mixed> $attributes */
    public function create(string $phone, string $body, SenderType $sender, array $attributes = [], ?string $idempotencyKey = null): Message
    {
        $data = array_merge([
            'direction' => MessageDirection::Outbound,
            'sender_type' => $sender,
            'phone' => Phone::normalize($phone) ?? $phone,
            'body' => $body,
            'provider' => $this->whatsapp->activeName(),
            'status' => MessageStatus::Pending,
            'idempotency_key' => $idempotencyKey,
        ], $attributes);

        if ($idempotencyKey === null) {
            return Message::create($data);
        }

        try {
            return Message::firstOrCreate(['idempotency_key' => $idempotencyKey], $data);
        } catch (UniqueConstraintViolationException) {
            return Message::where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    /**
     * Entrega el mensaje al proveedor. Seguro ante reintentos: un mensaje ya enviado no se vuelve a enviar.
     */
    public function deliver(Message $message): Message
    {
        $lock = Cache::lock('whatsapp:message:'.$message->id, 60);
        if (! $lock->get()) {
            return $message;
        }

        try {
            $message->refresh();
            if (! in_array($message->status, [MessageStatus::Pending, MessageStatus::Queued], true) || $message->provider_message_id) {
                return $message;
            }

            $provider = $this->whatsapp->provider($message->provider);
            $message->increment('attempts');

            try {
                $result = $this->dispatchToProvider($message, $provider);
            } catch (Throwable $e) {
                report($e);
                $result = SendResult::failed('EXCEPTION', Str::limit($e->getMessage(), 450), retryable: true);
            }

            $this->applyResult($message, $result);

            return $message;
        } finally {
            $lock->release();
        }
    }

    public function applyResult(Message $message, SendResult $result): void
    {
        if ($result->success) {
            $message->forceFill([
                'status' => $result->status,
                'provider_message_id' => $result->providerMessageId,
                'sent_at' => now(),
                'delivered_at' => in_array($result->status, [MessageStatus::Delivered, MessageStatus::Read], true) ? now() : null,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            return;
        }

        $message->forceFill([
            'status' => $result->retryable ? $message->status : MessageStatus::Failed,
            'error_code' => $result->errorCode,
            'error_message' => Str::limit((string) $result->errorMessage, 490),
            'failed_at' => $result->retryable ? null : now(),
            'meta' => array_merge($message->meta ?? [], ['retryable' => $result->retryable]),
        ])->save();
    }

    public function markFailed(Message $message, string $reason): void
    {
        $message->forceFill([
            'status' => MessageStatus::Failed,
            'failed_at' => now(),
            'error_message' => $message->error_message ?? $reason,
        ])->save();
    }

    /**
     * Fuera de la ventana de 24 h, WhatsApp solo permite plantillas aprobadas. Si la plantilla está aprobada en el
     * proveedor activo (nombre en Meta o Content SID en Twilio) se envía como plantilla, pasando los valores de las
     * variables en orden de aparición ({{1}}, {{2}}…).
     */
    private function dispatchToProvider(Message $message, $provider): SendResult
    {
        $message->loadMissing(['template', 'conversation', 'customer']);
        $template = $message->template;
        $insideWindow = $message->conversation?->withinServiceWindow() ?? false;
        $approvedId = $template?->approvedIdFor($provider->name());

        if ($approvedId && ! $provider->isSimulation() && ! $insideWindow) {
            return $provider->sendTemplate(
                $message->phone,
                $approvedId,
                $template->provider_template_language ?: 'es',
                $this->renderer->orderedValues($template->body, $message->customer),
                $message->body,
            );
        }

        return $provider->sendText($message->phone, $message->body);
    }
}
