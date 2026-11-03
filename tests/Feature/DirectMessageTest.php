<?php

namespace Tests\Feature;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Livewire\Customers\Show;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Services\Settings;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\Icons;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase(demoCustomers: 8);
        app(Settings::class)->set('whatsapp.simulator_failure_rate', 0);
    }

    private function maria(): Customer
    {
        return Customer::where('dui', '01234567-8')->firstOrFail();
    }

    public function test_send_individual_message_from_customer_page(): void
    {
        $admin = $this->admin();
        $customer = $this->maria();

        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()->assertSee('Enviar mensaje');

        Livewire::actingAs($admin)->test(Show::class, ['customer' => $customer])
            ->call('openMessage')
            ->assertSet('showMessageModal', true)
            ->set('messageBody', 'Hola María, le escribimos para confirmar su visita de mañana.')
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSet('showMessageModal', false)
            ->assertDispatched('toast');

        $message = Message::where('customer_id', $customer->id)->where('sender_type', SenderType::Agent)->firstOrFail();
        $this->assertSame(MessageStatus::Delivered, $message->status);
        $this->assertSame($admin->id, $message->user_id);
        $this->assertNull($message->campaign_id, 'Es un mensaje individual, no de campaña');

        $conversation = Conversation::where('phone', $customer->whatsapp_number)->firstOrFail();
        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame(ConversationStatus::InProgress, $conversation->status);
        $this->assertSame($admin->id, $conversation->assigned_to);
        $this->assertTrue($conversation->entries()->where('message_id', $message->id)->exists());

        // Doble clic: el mismo texto en menos de un minuto se rechaza
        Livewire::actingAs($admin)->test(Show::class, ['customer' => $customer])
            ->call('openMessage')
            ->set('messageBody', 'Hola María, le escribimos para confirmar su visita de mañana.')
            ->call('sendMessage')
            ->assertHasErrors('messageBody');
        $this->assertSame(1, Message::where('customer_id', $customer->id)->where('sender_type', SenderType::Agent)->count());
    }

    public function test_template_is_rendered_with_customer_data(): void
    {
        $template = MessageTemplate::where('name', 'Cobranza · Recordatorio amigable')->first();

        Livewire::actingAs($this->admin())->test(Show::class, ['customer' => $this->maria()])
            ->call('openMessage')
            ->set('messageTemplateId', (string) $template->id)
            ->assertSet('messageBody', fn ($body) => str_contains($body, 'Hola María') && str_contains($body, '$430.00'));
    }

    public function test_promotional_template_requires_consent(): void
    {
        $customer = $this->maria();
        $customer->update(['accepts_promotions' => false]);
        $promo = MessageTemplate::where('category', 'promotion')->first();

        Livewire::actingAs($this->admin())->test(Show::class, ['customer' => $customer])
            ->call('openMessage')
            ->set('messageTemplateId', (string) $promo->id)
            ->call('sendMessage')
            ->assertHasErrors('messageBody');

        $this->assertSame(0, Message::where('customer_id', $customer->id)->count());
    }

    public function test_real_provider_outside_24h_window_requires_approved_template(): void
    {
        $s = app(Settings::class);
        $s->setMany(['whatsapp.provider' => 'meta', 'whatsapp.meta_phone_number_id' => '1', 'whatsapp.meta_access_token' => 't']);
        app(WhatsAppManager::class)->forget();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.D1']]], 200)]);

        $component = Livewire::actingAs($this->admin())->test(Show::class, ['customer' => $this->maria()])
            ->call('openMessage')
            ->set('messageBody', 'Texto libre')
            ->call('sendMessage')
            ->assertHasErrors('messageBody');
        Http::assertNothingSent();

        $template = MessageTemplate::where('name', 'Cobranza · Recordatorio amigable')->first();
        $template->update(['provider_template_name' => 'recordatorio_pago_v1']);
        $component->set('messageTemplateId', (string) $template->id)->call('sendMessage')->assertHasNoErrors();

        Http::assertSent(fn ($r) => $r['type'] === 'template' && $r['template']['name'] === 'recordatorio_pago_v1');
    }

    public function test_twilio_content_sid_from_template_editor_is_used_outside_window(): void
    {
        $s = app(Settings::class);
        $s->setMany(['whatsapp.provider' => 'twilio', 'whatsapp.twilio_account_sid' => 'AC1', 'whatsapp.twilio_auth_token' => 'tok', 'whatsapp.twilio_from_number' => '+14155238886']);
        app(WhatsAppManager::class)->forget();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1', 'status' => 'queued'], 201)]);
        $template = MessageTemplate::where('name', 'Cobranza · Atención urgente')->firstOrFail();

        // SID inválido y luego válido (con espacios pegados desde la consola)
        Livewire::actingAs($this->admin())->test(\App\Livewire\Templates\Index::class)
            ->call('edit', $template->id)
            ->set('form.twilio_content_sid', 'abc123')
            ->call('save')
            ->assertHasErrors('form.twilio_content_sid')
            ->set('form.twilio_content_sid', '  HX8bd92f91d571d5d819df4b64f2602ec6 ')
            ->call('save')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertSame('HX8bd92f91d571d5d819df4b64f2602ec6', $template->twilio_content_sid);
        $this->assertNull($template->provider_template_name, 'El campo de Meta queda independiente');
        $this->assertSame('HX8bd92f91d571d5d819df4b64f2602ec6', $template->approvedIdFor('twilio'));
        $this->assertNull($template->approvedIdFor('meta'));

        Livewire::actingAs($this->admin())->test(Show::class, ['customer' => $this->maria()])
            ->call('openMessage')
            ->set('messageTemplateId', (string) $template->id)
            ->call('sendMessage')
            ->assertHasNoErrors();

        Http::assertSent(function ($r) {
            $vars = json_decode($r['ContentVariables'] ?? '{}', true);

            return $r['ContentSid'] === 'HX8bd92f91d571d5d819df4b64f2602ec6'
                && $vars['1'] === 'María Fernanda López Hernández'
                && $vars['2'] === 'CLI-000001'
                && $vars['3'] === '430.00';
        });
    }

    public function test_agent_without_send_permission_cannot_send(): void
    {
        $user = $this->userWithRole(Permissions::ROLE_AGENT);
        $user->roles->first()->revokePermissionTo('messages.send');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh())->get(route('customers.show', $this->maria()))->assertOk()->assertDontSee('Enviar mensaje');
        Livewire::actingAs($user->fresh())->test(Show::class, ['customer' => $this->maria()])->call('openMessage')->assertForbidden();
    }

    public function test_icon_component_renders_inline_svg(): void
    {
        $html = Icons::svg('users', 'class="size-4"');
        $this->assertStringStartsWith('<svg ', $html);
        $this->assertStringContainsString('class="size-4"', $html);
        $this->assertStringContainsString('<circle', $html);
        $this->assertSame('', Icons::svg('../../etc/passwd'));
        $this->assertStringContainsString('<svg', view('components.icon', ['name' => 'bell'])->render());
    }
}
