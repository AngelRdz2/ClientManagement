<?php

namespace App\Services\Conversations;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Message;
use App\Notifications\SystemNotification;
use App\Services\ActivityLogger;
use App\Services\Chatbot\ChatbotEngine;
use App\Services\Chatbot\IntentDetector;
use App\Services\Messaging\MessageSender;
use App\Services\NotificationService;
use App\Services\WhatsApp\Data\InboundMessage;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Procesa los mensajes que llegan de WhatsApp (webhook real o simulador).
 */
class InboundMessageService
{
    public function __construct(
        private ChatbotEngine $chatbot,
        private ConversationService $conversations,
        private MessageSender $sender,
        private NotificationService $notifications,
    ) {}

    public function receive(InboundMessage $inbound, string $provider, bool $simulated = false): ?Message
    {
        // Idempotencia: los proveedores pueden reenviar el mismo webhook.
        if ($inbound->providerMessageId && Message::where('provider_message_id', $inbound->providerMessageId)->exists()) {
            return null;
        }

        $phone = Phone::normalize($inbound->from);
        if (! $phone || trim($inbound->body) === '') {
            return null;
        }

        [$conversation, $message, $isNew] = DB::transaction(function () use ($inbound, $provider, $simulated, $phone) {
            $customer = $this->identifyCustomer($phone);

            $conversation = Conversation::lockForUpdate()->firstOrNew(['phone' => $phone]);
            $isNew = ! $conversation->exists;
            if ($isNew) {
                $conversation->fill(['provider' => $provider, 'status' => ConversationStatus::Bot, 'is_simulated' => $simulated]);
            }
            $conversation->fill([
                'customer_id' => $conversation->customer_id ?? $customer?->id,
                'contact_name' => $inbound->contactName ?: $conversation->contact_name,
                'last_message_at' => now(),
                'last_inbound_at' => now(),
                'last_message_preview' => Str::limit($inbound->body, 120),
                'unread_count' => $conversation->unread_count + 1,
            ])->save();

            $message = Message::create([
                'customer_id' => $conversation->customer_id,
                'conversation_id' => $conversation->id,
                'direction' => MessageDirection::Inbound,
                'sender_type' => SenderType::Customer,
                'phone' => $phone,
                'body' => $inbound->body,
                'provider' => $provider,
                'provider_message_id' => $inbound->providerMessageId,
                'status' => MessageStatus::Received,
                'meta' => $inbound->type !== 'text' ? ['type' => $inbound->type] : null,
            ]);

            ConversationMessage::create([
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'type' => 'message',
                'sender_type' => SenderType::Customer,
                'body' => $inbound->body,
            ]);

            return [$conversation, $message, $isNew];
        });

        if ($conversation->status === ConversationStatus::Closed) {
            $this->conversations->reopen($conversation);
        }

        if ($isNew) {
            $this->notifications->toPermission('conversations.attend', new SystemNotification(
                'new_conversation', 'Nueva conversación de WhatsApp',
                'Nueva conversación con '.$conversation->displayName().($simulated ? ' (simulada)' : '').'.',
                'message-circle', 'blue', route('conversations.index', ['c' => $conversation->id]),
            ));
        }

        if ($this->isOptOut($inbound->body)) {
            $this->optOut($conversation);

            return $message;
        }

        $this->chatbot->handle($conversation->fresh(), $inbound->body);

        return $message;
    }

    private function identifyCustomer(string $phone): ?Customer
    {
        // Solo identifica (para mostrar al asesor); nunca autoriza mostrar información financiera.
        return Customer::where('whatsapp_number', $phone)->orderByRaw("status = 'active' desc")->first();
    }

    private function isOptOut(string $body): bool
    {
        $text = IntentDetector::normalize($body);
        $keywords = array_filter(array_map(fn ($k) => IntentDetector::normalize($k), explode(',', (string) setting('communication.opt_out_keywords', ''))));

        return in_array($text, $keywords, true);
    }

    /** El cliente solicitó no recibir más mensajes promocionales/masivos. */
    private function optOut(Conversation $conversation): void
    {
        if ($conversation->customer_id) {
            $customer = Customer::find($conversation->customer_id);
            $customer?->update(['whatsapp_opted_out_at' => now(), 'accepts_promotions' => false, 'promotions_consent_at' => null]);
            if ($customer) {
                ActivityLogger::log('customer.opted_out', "{$customer->full_name} solicitó la baja de mensajes por WhatsApp", $customer, [], null);
            }
        }
        $this->conversations->event($conversation, '🚫 El cliente solicitó la baja de mensajes masivos.');
        $this->sender->replyInConversation($conversation, (string) setting('communication.opt_out_message'), SenderType::System);
    }
}
