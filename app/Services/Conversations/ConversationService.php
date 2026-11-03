<?php

namespace App\Services\Conversations;

use App\Enums\ChatbotState;
use App\Enums\ConversationStatus;
use App\Enums\SenderType;
use App\Enums\TemplateCategory;
use App\Models\ChatbotSession;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\ActivityLogger;
use App\Services\Messaging\BusinessHours;
use App\Services\Messaging\MessageSender;
use App\Services\Messaging\TemplateRenderer;
use App\Services\NotificationService;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Operaciones sobre conversaciones: transferencia a asesor, asignación, respuesta manual, devolución al bot y cierre.
 */
class ConversationService
{
    public function __construct(
        private MessageSender $sender,
        private NotificationService $notifications,
        private TemplateRenderer $renderer,
        private WhatsAppManager $whatsapp,
    ) {}

    /** El cliente pidió hablar con una persona: el bot deja de responder y se alerta al equipo. */
    public function requestAgent(Conversation $conversation, string $reason = 'El cliente solicitó hablar con un asesor'): void
    {
        if (in_array($conversation->status, [ConversationStatus::PendingAgent, ConversationStatus::Assigned, ConversationStatus::InProgress], true)) {
            return;
        }

        DB::transaction(function () use ($conversation, $reason) {
            $conversation->forceFill(['status' => ConversationStatus::PendingAgent, 'agent_requested_at' => now(), 'assigned_to' => null])->save();
            $this->log($conversation, 'agent_requested', null, null, $reason);
            $this->event($conversation, '🙋 '.$reason.'. El bot dejó de responder.');
            ChatbotSession::where('conversation_id', $conversation->id)->update(['state' => ChatbotState::Idle, 'pending_intent' => null]);
        });

        $open = BusinessHours::isOpen();
        $message = $open ? setting('chatbot.agent_transfer_message') : setting('chatbot.out_of_hours_message');
        $this->sender->replyInConversation($conversation, $this->renderer->render((string) $message, $conversation->customer), SenderType::Bot);

        // La notificación no incluye información financiera.
        $this->notifications->toPermission('conversations.attend', new SystemNotification(
            'agent_requested',
            'Cliente solicita un asesor',
            $conversation->displayName().' está esperando atención'.($open ? '.' : ' (fuera de horario).'),
            'headset', 'red', route('conversations.index', ['c' => $conversation->id]),
        ));
    }

    public function take(Conversation $conversation, User $user): void
    {
        $this->assign($conversation, $user, $user, 'taken');
    }

    public function assign(Conversation $conversation, User $agent, User $by, string $action = 'assigned'): void
    {
        if (! $agent->can('conversations.attend')) {
            throw new RuntimeException('El usuario seleccionado no tiene permiso para atender conversaciones.');
        }

        DB::transaction(function () use ($conversation, $agent, $by, $action) {
            $conversation->forceFill([
                'status' => $action === 'taken' ? ConversationStatus::InProgress : ConversationStatus::Assigned,
                'assigned_to' => $agent->id,
                'closed_at' => null,
                'unread_count' => $action === 'taken' ? 0 : $conversation->unread_count,
            ])->save();
            $this->log($conversation, $action, $agent, $by);
            $this->event($conversation, $action === 'taken' ? "👤 {$agent->name} tomó la conversación." : "👤 {$by->name} asignó la conversación a {$agent->name}.");
        });

        ActivityLogger::log('conversation.'.$action, ($action === 'taken' ? "{$agent->name} tomó" : "{$by->name} asignó a {$agent->name}").' la conversación con '.$conversation->displayName(), $conversation, [], $by->id);

        if ($action !== 'taken') {
            $this->notifications->toUser($agent, new SystemNotification(
                'conversation_assigned', 'Nueva conversación asignada',
                "{$by->name} te asignó la conversación con {$conversation->displayName()}.",
                'messages-square', 'indigo', route('conversations.index', ['c' => $conversation->id]),
            ));
        }
    }

    /** Respuesta manual de un asesor. */
    public function sendAgentMessage(Conversation $conversation, User $agent, string $body): Message
    {
        $provider = $this->whatsapp->provider($conversation->is_simulated ? 'simulator' : null);
        if (! $provider->isSimulation() && ! $conversation->withinServiceWindow()) {
            throw new RuntimeException('Han pasado más de 24 horas desde el último mensaje del cliente. WhatsApp solo permite enviar una plantilla aprobada fuera de la ventana de atención.');
        }

        if (in_array($conversation->status, [ConversationStatus::Bot, ConversationStatus::PendingAgent, ConversationStatus::Assigned, ConversationStatus::Closed], true)) {
            // Al responder, el asesor queda a cargo de la conversación.
            if ($conversation->assigned_to !== $agent->id || $conversation->status !== ConversationStatus::Assigned) {
                $this->log($conversation, 'taken', $agent, $agent);
                $this->event($conversation, "👤 {$agent->name} está atendiendo la conversación.");
            }
            $conversation->forceFill(['status' => ConversationStatus::InProgress, 'assigned_to' => $agent->id, 'closed_at' => null])->save();
        }

        $conversation->forceFill(['unread_count' => 0])->save();

        return $this->sender->replyInConversation($conversation, $body, SenderType::Agent, $agent);
    }

    /**
     * Mensaje individual iniciado por un usuario desde la ficha del cliente (no masivo).
     * Se registra en la conversación del cliente para que su respuesta llegue a la bandeja.
     *
     * @throws RuntimeException si no se puede enviar según las reglas de comunicación o del proveedor
     */
    public function sendDirectMessage(Customer $customer, User $user, string $body, ?MessageTemplate $template = null, bool $assignToMe = true): Message
    {
        if (! $customer->whatsapp_number) {
            throw new RuntimeException('El cliente no tiene un número de WhatsApp válido.');
        }
        if ($template?->category === TemplateCategory::Promotion && ! $customer->canReceivePromotions()) {
            throw new RuntimeException('El cliente no autorizó recibir promociones (o solicitó la baja). No se puede enviar una plantilla promocional.');
        }

        $conversation = Conversation::firstOrCreate(
            ['phone' => $customer->whatsapp_number],
            ['customer_id' => $customer->id, 'provider' => $this->whatsapp->activeName(), 'status' => ConversationStatus::Bot, 'is_simulated' => false],
        );
        if ($conversation->customer_id === null) {
            $conversation->forceFill(['customer_id' => $customer->id])->save();
        }

        // Fuera de la ventana de 24 h, WhatsApp (Meta) solo admite plantillas aprobadas.
        $provider = $this->whatsapp->provider($conversation->is_simulated ? 'simulator' : null);
        if (! $provider->isSimulation() && ! $conversation->withinServiceWindow() && ! $template?->approvedIdFor($provider->name())) {
            $where = $provider->name() === 'twilio' ? 'su Content SID de Twilio' : 'su nombre aprobado en Meta';
            throw new RuntimeException("El cliente no ha escrito en las últimas 24 horas. WhatsApp solo permite iniciar la conversación con una plantilla aprobada: elige una plantilla marcada con ✓ (configura {$where} en Plantillas › Editar).");
        }

        // Evita envíos duplicados por doble clic o reintentos.
        $duplicate = Message::query()->where('conversation_id', $conversation->id)->where('sender_type', SenderType::Agent)
            ->where('body', $body)->where('created_at', '>=', now()->subMinute())->exists();
        if ($duplicate) {
            throw new RuntimeException('Este mismo mensaje ya se envió a este cliente hace menos de un minuto.');
        }

        if ($conversation->status === ConversationStatus::Closed) {
            $this->reopen($conversation, $user);
        }
        if ($assignToMe && $conversation->assigned_to !== $user->id) {
            $conversation->forceFill(['status' => ConversationStatus::InProgress, 'assigned_to' => $user->id, 'closed_at' => null])->save();
            $this->log($conversation, 'taken', $user, $user, 'Mensaje directo desde la ficha del cliente');
            $this->event($conversation, "👤 {$user->name} inició un mensaje directo desde la ficha del cliente.");
        }

        $message = $this->sender->replyInConversation($conversation, $body, SenderType::Agent, $user, [
            'message_template_id' => $template?->id,
            'meta' => ['direct' => true],
        ]);

        ActivityLogger::log('message.direct', "Envió un mensaje directo a {$customer->full_name}".($template ? " (plantilla “{$template->name}”)" : ''), $customer, ['message_id' => $message->id]);

        return $message;
    }

    public function addNote(Conversation $conversation, User $user, string $body): void
    {
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'type' => 'note',
            'sender_type' => SenderType::Agent,
            'user_id' => $user->id,
            'body' => $body,
        ]);
    }

    /** El asesor devuelve la conversación al bot. */
    public function returnToBot(Conversation $conversation, User $user, bool $notifyCustomer = true): void
    {
        DB::transaction(function () use ($conversation, $user) {
            $conversation->forceFill(['status' => ConversationStatus::Bot, 'assigned_to' => null, 'agent_requested_at' => null, 'closed_at' => null])->save();
            $this->log($conversation, 'returned_to_bot', null, $user);
            $this->event($conversation, "🤖 {$user->name} devolvió la conversación al bot.");
            ChatbotSession::where('conversation_id', $conversation->id)->update(['state' => ChatbotState::Idle, 'pending_intent' => null]);
        });

        if ($notifyCustomer) {
            $this->sender->replyInConversation($conversation, (string) setting('chatbot.returned_to_bot_message'), SenderType::Bot);
        }
    }

    public function close(Conversation $conversation, User $user): void
    {
        DB::transaction(function () use ($conversation, $user) {
            $conversation->forceFill(['status' => ConversationStatus::Closed, 'closed_at' => now(), 'unread_count' => 0])->save();
            $this->log($conversation, 'closed', $conversation->assignee()->first(), $user);
            $this->event($conversation, "✅ {$user->name} cerró la conversación.");
            ChatbotSession::where('conversation_id', $conversation->id)->update([
                'state' => ChatbotState::Idle, 'pending_intent' => null, 'customer_id' => null, 'verified_at' => null, 'verification_expires_at' => null,
            ]);
        });
    }

    public function reopen(Conversation $conversation, ?User $user = null): void
    {
        $conversation->forceFill(['status' => ConversationStatus::Bot, 'closed_at' => null, 'assigned_to' => null])->save();
        $this->log($conversation, 'reopened', null, $user);
        $this->event($conversation, '↩️ La conversación se reabrió'.($user ? " ({$user->name})." : ' por un nuevo mensaje del cliente.'));
    }

    public function event(Conversation $conversation, string $body): void
    {
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'type' => 'event',
            'sender_type' => SenderType::System,
            'body' => $body,
        ]);
    }

    private function log(Conversation $conversation, string $action, ?User $agent, ?User $by, ?string $notes = null): void
    {
        ConversationAssignment::create([
            'conversation_id' => $conversation->id,
            'user_id' => $agent?->id,
            'performed_by' => $by?->id,
            'action' => $action,
            'notes' => $notes,
        ]);
    }
}
