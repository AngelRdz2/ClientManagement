<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Livewire\Reports\Index as Reports;
use App\Livewire\Settings\Index as SettingsPage;
use App\Livewire\Users\Index as Users;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Messaging\MessageSender;
use App\Services\Reports\ReportService;
use App\Services\Settings;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase5IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase(demoCustomers: 15);
    }

    private function configureMeta(): void
    {
        $s = app(Settings::class);
        $s->set('whatsapp.provider', 'meta');
        $s->set('whatsapp.meta_phone_number_id', '123456789');
        $s->set('whatsapp.meta_access_token', 'test-token');
        $s->set('whatsapp.meta_app_secret', 'app-secret');
        $s->set('whatsapp.meta_verify_token', 'verify-me');
        app(WhatsAppManager::class)->forget();
    }

    public function test_all_reports_render_and_export(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('reports.index'))->assertOk();

        foreach (array_keys(ReportService::REPORTS) as $report) {
            Livewire::actingAs($admin)->test(Reports::class)->set('report', $report)->assertOk();
            $this->actingAs($admin)
                ->get(route('reports.export', ['report' => $report, 'from' => now()->subYear()->toDateString(), 'to' => now()->toDateString()]))
                ->assertOk()
                ->assertDownload();
        }

        $data = app(ReportService::class)->run('customers_by_status', null, null);
        $this->assertEqualsWithDelta((float) Customer::active()->whereNotNull('delinquency_status_id')->sum('outstanding_balance'), collect($data['rows'])->sum(fn ($r) => $r[6]), 0.01);
    }

    public function test_meta_webhook_verification_challenge(): void
    {
        $this->configureMeta();
        $this->get('/api/webhooks/whatsapp/meta?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=12345')->assertOk()->assertSee('12345');
        $this->get('/api/webhooks/whatsapp/meta?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')->assertForbidden();
        $this->get('/api/webhooks/whatsapp/desconocido')->assertNotFound();
    }

    public function test_meta_webhook_signature_inbound_reply_and_status(): void
    {
        $this->configureMeta();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.REPLY1']]], 200)]);

        $payload = json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => '50370123456', 'profile' => ['name' => 'María']]],
            'messages' => [['from' => '50370123456', 'id' => 'wamid.IN1', 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => 'Hola']]],
        ]]]]]]);

        // Firma inválida
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalida'], $payload)->assertForbidden();
        $this->assertSame(0, Message::count());

        // Firma válida
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'app-secret');
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature], $payload)->assertOk();

        $conversation = Conversation::where('phone', '50370123456')->firstOrFail();
        $this->assertFalse($conversation->is_simulated);
        $this->assertSame('María', $conversation->contact_name);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/123456789/messages')
            && $r->hasHeader('Authorization', 'Bearer test-token')
            && $r['type'] === 'text' && $r['to'] === '50370123456');

        $reply = Message::where('provider_message_id', 'wamid.REPLY1')->firstOrFail();
        $this->assertSame(SenderType::Bot, $reply->sender_type);
        $this->assertSame(MessageStatus::Sent, $reply->status);

        // Estado de lectura reportado por Meta
        $status = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.REPLY1', 'status' => 'read', 'timestamp' => (string) time()]]]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $status, 'app-secret')], $status)->assertOk();
        $this->assertSame(MessageStatus::Read, $reply->fresh()->status);
    }

    public function test_meta_uses_approved_template_outside_service_window(): void
    {
        $this->configureMeta();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T1']]], 200)]);
        $template = MessageTemplate::where('name', 'Cobranza · Recordatorio amigable')->first();
        $template->update(['provider_template_name' => 'recordatorio_pago_v1', 'provider_template_language' => 'es']);
        $customer = Customer::where('dui', '01234567-8')->first();

        $sender = app(MessageSender::class);
        $message = $sender->create($customer->whatsapp_number, 'texto', SenderType::Campaign, ['customer_id' => $customer->id, 'message_template_id' => $template->id]);
        $sender->deliver($message);

        Http::assertSent(fn (HttpRequest $r) => $r['type'] === 'template'
            && $r['template']['name'] === 'recordatorio_pago_v1'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'María');
        $this->assertSame('wamid.T1', $message->fresh()->provider_message_id);
    }

    public function test_meta_error_marks_message_failed(): void
    {
        $this->configureMeta();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131026, 'message' => 'Message undeliverable']], 400)]);
        $sender = app(MessageSender::class);
        $message = $sender->create('50370123456', 'Hola', SenderType::Agent);
        $sender->deliver($message);
        $this->assertSame(MessageStatus::Failed, $message->fresh()->status);
        $this->assertSame('131026', $message->fresh()->error_code);
    }

    public function test_twilio_webhook_signature_and_send(): void
    {
        $s = app(Settings::class);
        $s->setMany(['whatsapp.provider' => 'twilio', 'whatsapp.twilio_account_sid' => 'AC123', 'whatsapp.twilio_auth_token' => 'tw-secret', 'whatsapp.twilio_from_number' => '+14155238886']);
        app(WhatsAppManager::class)->forget();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201)]);

        $url = route('webhooks.whatsapp', 'twilio');
        $params = ['From' => 'whatsapp:+50370123456', 'Body' => 'Hola', 'MessageSid' => 'SMIN1', 'ProfileName' => 'María', 'SmsStatus' => 'received'];
        $data = $url;
        ksort($params);
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }
        $signature = base64_encode(hash_hmac('sha1', $data, 'tw-secret', true));

        $this->post('/api/webhooks/whatsapp/twilio', $params, ['X-Twilio-Signature' => 'mala'])->assertForbidden();
        $this->post('/api/webhooks/whatsapp/twilio', $params, ['X-Twilio-Signature' => $signature])->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/Accounts/AC123/Messages.json') && $r['To'] === 'whatsapp:+50370123456' && $r['From'] === 'whatsapp:+14155238886');
        $this->assertTrue(Message::where('provider_message_id', 'SM123')->exists());
    }

    public function test_secret_credentials_are_encrypted_and_env_takes_precedence(): void
    {
        Livewire::actingAs($this->admin())->test(SettingsPage::class)
            ->set('tab', 'whatsapp')
            ->set('secrets.whatsapp__meta_access_token', 'super-secreto')
            ->call('save')
            ->assertHasNoErrors();

        $raw = SystemSetting::where('key', 'whatsapp.meta_access_token')->value('value');
        $this->assertNotSame('super-secreto', $raw);
        $this->assertStringNotContainsString('super-secreto', $raw);
        $this->assertSame('super-secreto', app(Settings::class)->get('whatsapp.meta_access_token'));

        config(['whatsapp.env.WHATSAPP_META_ACCESS_TOKEN' => 'desde-env']);
        $this->assertSame('desde-env', app(Settings::class)->get('whatsapp.meta_access_token'));
    }

    public function test_settings_tabs_save(): void
    {
        $component = Livewire::actingAs($this->admin())->test(SettingsPage::class);
        $component->set('values.company__name', 'Mi Empresa S.A.')->call('save')->assertHasNoErrors();
        $this->assertSame('Mi Empresa S.A.', setting('company.name'));

        foreach (['whatsapp', 'hours', 'notifications', 'communication', 'collection'] as $tab) {
            $component->set('tab', $tab)->call('save')->assertHasNoErrors();
        }
        $this->actingAs($this->admin())->get(route('settings.index', ['tab' => 'whatsapp']))->assertOk()->assertSee('/api/webhooks/whatsapp/meta');
    }

    public function test_user_management_and_permissions(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(Users::class)
            ->call('create')
            ->set('form.name', 'Nuevo Asesor')
            ->set('form.email', 'nuevo@crm.test')
            ->set('form.role', Permissions::ROLE_AGENT)
            ->set('form.password', 'clave1234')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'nuevo@crm.test')->firstOrFail();
        $this->assertTrue($user->hasRole(Permissions::ROLE_AGENT));
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('conversations.index'))->assertOk();

        // No puede desactivarse a sí mismo
        Livewire::actingAs($admin)->test(Users::class)->call('toggleActive', $admin->id)->assertDispatched('alert');
        $this->assertTrue($admin->fresh()->is_active);

        // Cambiar permisos de un rol aplica de inmediato
        $agentRoleId = Role::findByName(Permissions::ROLE_AGENT)->id;
        Livewire::actingAs($admin)->test(Users::class)
            ->set('tab', 'roles')
            ->set("matrix.{$agentRoleId}", [Permissions::CUSTOMERS_VIEW, Permissions::CONVERSATIONS_ATTEND, Permissions::REPORTS_VIEW])
            ->call('savePermissions');
        $this->assertTrue($user->fresh()->can(Permissions::REPORTS_VIEW));
        $this->assertFalse($user->fresh()->can(Permissions::MESSAGES_SEND));
    }
}
