<?php

namespace Tests\Feature;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Livewire\Conversations\Inbox;
use App\Livewire\Dashboard\AgentAlerts;
use App\Livewire\Simulator;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Services\Conversations\InboundMessageService;
use App\Services\Conversations\StatusUpdateService;
use App\Services\Settings;
use App\Services\WhatsApp\Data\InboundMessage;
use App\Services\WhatsApp\Data\StatusUpdate;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class Phase4ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private const MARIA_PHONE = '50370123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase(demoCustomers: 15);
        app(Settings::class)->set('whatsapp.simulator_failure_rate', 0);
    }

    private function say(string $text, string $phone = self::MARIA_PHONE): Conversation
    {
        app(InboundMessageService::class)->receive(
            new InboundMessage($phone, $text, 'sim_in_'.Str::random(12)), 'simulator', simulated: true
        );

        return Conversation::where('phone', $phone)->firstOrFail();
    }

    private function lastBotReply(Conversation $conversation): string
    {
        return (string) $conversation->entries()->whereIn('sender_type', [SenderType::Bot->value, SenderType::System->value])->where('type', 'message')->latest('id')->value('body');
    }

    public function test_example_conversation_shows_real_balance_after_verification(): void
    {
        $c = $this->say('Hola, quisiera saber cuánto debo.');
        $this->assertStringContainsString('ingresa tu número de DUI', $this->lastBotReply($c));
        $this->assertSame(Customer::where('dui', '01234567-8')->value('id'), $c->customer_id, 'El número se identifica, pero no se muestran datos sin verificar');

        $c = $this->say('01234567-8');
        $reply = $this->lastBotReply($c);

        $this->assertStringContainsString('Hemos encontrado tu información', $reply);
        $this->assertStringContainsString('Actualmente tienes 2 artículos', $reply);
        $this->assertStringContainsString('Televisor Samsung 55" 4K', $reply);
        $this->assertStringContainsString('Saldo pendiente: $250.00', $reply);
        $this->assertStringContainsString('Cuotas pendientes: 5', $reply);
        $this->assertStringContainsString('Refrigeradora LG 14 pies', $reply);
        $this->assertStringContainsString('Saldo pendiente: $180.00', $reply);
        $this->assertStringContainsString('Saldo total pendiente: $430.00', $reply);
        $this->assertTrue($c->chatbotSession->isVerified());

        // Detalle del artículo 1 y otras consultas dentro de la sesión verificada
        $this->assertStringContainsString('Contrato:', $this->lastBotReply($this->say('1')));
        $this->assertStringContainsString('Cuotas pagadas', $this->lastBotReply($this->say('cuotas pendientes')));
        $this->assertStringContainsString('próximas fechas de pago', $this->lastBotReply($this->say('cuando es mi fecha de pago')));

        // Todas las respuestas quedan registradas como mensajes del bot en el simulador
        $this->assertSame(0, Message::where('conversation_id', $c->id)->where('sender_type', SenderType::Bot)->where('provider', '!=', 'simulator')->count());
    }

    public function test_no_financial_info_from_unregistered_number_and_lock_after_failed_attempts(): void
    {
        $stranger = '50379998888';
        $this->say('saldo', $stranger);
        $c = $this->say('01234567-8', $stranger);
        $reply = $this->lastBotReply($c);

        $this->assertStringNotContainsString('$430.00', $reply);
        $this->assertStringContainsString('número de teléfono registrado', $reply);
        $this->assertFalse((bool) $c->chatbotSession->isVerified());

        $this->say('01234567-8', $stranger);
        $c = $this->say('01234567-8', $stranger);
        $this->assertTrue($c->chatbotSession->fresh()->isLocked());
        $this->assertStringContainsString('bloqueamos la consulta', $this->lastBotReply($c));

        // Ningún mensaje enviado al desconocido contiene saldos
        $this->assertSame(0, Message::where('phone', $stranger)->where('body', 'like', '%$430%')->count());
    }

    public function test_otp_verification_sends_code_to_registered_phone(): void
    {
        app(Settings::class)->set('chatbot.verification_method', 'otp');

        $this->say('saldo');
        $c = $this->say('012345678');
        $this->assertStringContainsString('código de 6 dígitos', $this->lastBotReply($c));

        $otp = Message::where('meta->otp', true)->latest('id')->firstOrFail();
        $this->assertSame(self::MARIA_PHONE, $otp->phone);
        preg_match('/\*(\d{6})\*/', $otp->body, $m);

        $this->assertStringContainsString('no es correcto', $this->lastBotReply($this->say('000000')));
        $c = $this->say($m[1]);
        $this->assertStringContainsString('Saldo total pendiente: $430.00', $this->lastBotReply($c));
    }

    public function test_contract_number_verification(): void
    {
        app(Settings::class)->set('chatbot.verification_method', 'contract_number');
        $contract = Customer::where('dui', '01234567-8')->first()->articles()->value('contract_number');

        $stranger = '50371112222'; // con este método el número no necesita coincidir
        $this->say('mis articulos', $stranger);
        $this->say('01234567-8', $stranger);
        $c = $this->say(substr(preg_replace('/\D/', '', $contract), -4), $stranger);

        $this->assertStringContainsString('Estos son tus artículos', $this->lastBotReply($c));
    }

    public function test_transfer_to_agent_take_reply_and_return_to_bot(): void
    {
        $agent = User::where('email', 'asesor@crm.test')->first();

        $c = $this->say('Quiero hablar con una persona');
        $this->assertSame(ConversationStatus::PendingAgent, $c->status);
        $this->assertNotNull($c->agent_requested_at);
        $this->assertTrue($agent->notifications()->where('type', 'agent_requested')->exists());
        $this->assertStringNotContainsString('$', $agent->notifications()->where('type', 'agent_requested')->first()->data['body']);

        // El bot deja de responder
        $before = $c->entries()->where('sender_type', SenderType::Bot)->count();
        $this->say('¿Hola? ¿cuánto debo?');
        $this->assertSame($before, $c->entries()->where('sender_type', SenderType::Bot)->count());

        // Alerta en el dashboard
        Livewire::actingAs($agent)->test(AgentAlerts::class)->assertSee('solicita hablar con un asesor');

        // El asesor toma y responde
        Livewire::actingAs($agent)->test(Inbox::class)
            ->call('select', $c->id)
            ->call('take')
            ->set('body', 'Hola, soy Carlos. ¿En qué te ayudo?')
            ->call('send')
            ->assertHasNoErrors();

        $c->refresh();
        $this->assertSame(ConversationStatus::InProgress, $c->status);
        $this->assertSame($agent->id, $c->assigned_to);
        $this->assertTrue(ConversationAssignment::where('conversation_id', $c->id)->where('action', 'taken')->where('user_id', $agent->id)->exists());
        $agentMessage = Message::where('conversation_id', $c->id)->where('sender_type', SenderType::Agent)->first();
        $this->assertSame($agent->id, $agentMessage->user_id);
        $this->assertSame(MessageStatus::Delivered, $agentMessage->status);

        // Nota interna: no se envía al cliente
        Livewire::actingAs($agent)->test(Inbox::class)->call('select', $c->id)->set('noteMode', true)->set('body', 'Cliente pide prórroga')->call('send');
        $this->assertSame(0, Message::where('body', 'Cliente pide prórroga')->count());

        // Devolver al bot
        Livewire::actingAs($agent)->test(Inbox::class)->call('select', $c->id)->call('returnToBot');
        $this->assertSame(ConversationStatus::Bot, $c->fresh()->status);
        $this->assertStringContainsString('Puedo ayudarte', $this->lastBotReply($this->say('menu')));
    }

    public function test_opt_out_keyword(): void
    {
        $this->say('BAJA');
        $customer = Customer::where('whatsapp_number', self::MARIA_PHONE)->first();
        $this->assertNotNull($customer->whatsapp_opted_out_at);
        $this->assertFalse($customer->accepts_promotions);
    }

    public function test_inbound_webhook_is_idempotent_and_status_updates_only_advance(): void
    {
        $service = app(InboundMessageService::class);
        $in = new InboundMessage(self::MARIA_PHONE, 'Hola', 'wamid.TEST1');
        $service->receive($in, 'meta');
        $this->assertNull($service->receive($in, 'meta'));
        $this->assertSame(1, Message::where('provider_message_id', 'wamid.TEST1')->count());

        $out = Message::where('direction', 'outbound')->latest('id')->first();
        $statuses = app(StatusUpdateService::class);
        $statuses->apply(new StatusUpdate($out->provider_message_id, MessageStatus::Read));
        $statuses->apply(new StatusUpdate($out->provider_message_id, MessageStatus::Delivered));
        $this->assertSame(MessageStatus::Read, $out->fresh()->status);
        $this->assertNotNull($out->fresh()->read_at);
    }

    public function test_simulated_conversations_never_use_real_provider(): void
    {
        app(Settings::class)->set('whatsapp.provider', 'meta'); // sin credenciales
        app(WhatsAppManager::class)->forget();

        $c = $this->say('Hola');
        $reply = Message::where('conversation_id', $c->id)->where('sender_type', SenderType::Bot)->latest('id')->first();
        $this->assertSame('simulator', $reply->provider);
        $this->assertSame(MessageStatus::Delivered, $reply->status);
    }

    public function test_inbox_and_simulator_pages(): void
    {
        $admin = $this->admin();
        $c = $this->say('Hola');
        $this->actingAs($admin)->get(route('conversations.index', ['c' => $c->id]))->assertOk()->assertSee($c->displayName());
        $this->actingAs($admin)->get(route('simulator'))->assertOk()->assertSee('Entorno de simulación');
        $this->actingAs($admin)->get(route('chatbot.index'))->assertOk();

        Livewire::actingAs($admin)->test(Simulator::class)
            ->set('phone', '50375550000')
            ->set('text', 'Hola')
            ->call('send')
            ->assertHasNoErrors();
        $this->assertTrue(Conversation::where('phone', '50375550000')->where('is_simulated', true)->exists());

        foreach (['open', 'pending', 'mine', 'assigned', 'bot', 'closed'] as $filter) {
            Livewire::actingAs($admin)->test(Inbox::class)->set('filter', $filter)->assertOk();
        }
    }
}
