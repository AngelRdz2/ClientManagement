<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\MessageSender;
use App\Services\Settings;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\Webhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Twilio detrás de un túnel (herd share / ngrok): la firma se calcula con la URL pública, no con la local.
 */
class TwilioPublicUrlTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_URL = 'https://abc123.sharedwithexpose.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase(demoCustomers: 3);
        app(Settings::class)->setMany([
            'whatsapp.provider' => 'twilio', 'whatsapp.twilio_account_sid' => 'AC1',
            'whatsapp.twilio_auth_token' => 'tw-secret', 'whatsapp.twilio_from_number' => '+14155238886',
        ]);
        app(WhatsAppManager::class)->forget();
        Http::fake(['api.twilio.com/*' => fn () => Http::response(['sid' => 'SM'.bin2hex(random_bytes(8)), 'status' => 'queued'], 201)]);
    }

    private function sign(string $url, array $params): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return base64_encode(hash_hmac('sha1', $data, 'tw-secret', true));
    }

    public function test_inbound_reply_signed_with_public_tunnel_url_is_accepted_and_shown(): void
    {
        config(['whatsapp.public_url' => self::PUBLIC_URL]);
        $this->assertSame(self::PUBLIC_URL.'/api/webhooks/whatsapp/twilio', Webhooks::url('twilio'));

        $params = ['From' => 'whatsapp:+50370123456', 'Body' => 'Hola', 'MessageSid' => 'SMIN99', 'ProfileName' => 'María', 'SmsStatus' => 'received'];
        $signature = $this->sign(Webhooks::url('twilio'), $params);

        $this->post('/api/webhooks/whatsapp/twilio', $params, ['X-Twilio-Signature' => $signature])->assertOk();

        $inbound = Message::where('provider_message_id', 'SMIN99')->firstOrFail();
        $this->assertSame(MessageStatus::Received, $inbound->status);
        $this->assertSame('Hola', $inbound->body);
        $this->assertNotNull($inbound->customer_id, 'Se identifica al cliente por su número');
        $this->assertNotNull(Conversation::where('phone', '50370123456')->value('last_inbound_at'));

        // Aparece en la pestaña Mensajes de la ficha del cliente
        $this->actingAs($this->admin())
            ->get(route('customers.show', ['customer' => $inbound->customer_id, 'tab' => 'messages']))
            ->assertOk()->assertSee('Entrante');
    }

    public function test_signature_for_another_url_is_rejected(): void
    {
        config(['whatsapp.public_url' => self::PUBLIC_URL]);
        $params = ['From' => 'whatsapp:+50370123456', 'Body' => 'Hola', 'MessageSid' => 'SMX', 'SmsStatus' => 'received'];

        $this->post('/api/webhooks/whatsapp/twilio', $params, ['X-Twilio-Signature' => $this->sign('https://otro-sitio.com/hook', $params)])
            ->assertForbidden();
        $this->assertFalse(Message::where('provider_message_id', 'SMX')->exists());
    }

    public function test_status_callback_only_sent_with_public_url_and_updates_delivery(): void
    {
        $sender = app(MessageSender::class);

        // Sin URL pública (local .test): no se manda StatusCallback (Twilio no podría alcanzarlo)
        $sender->deliver($sender->create('50370123456', 'Uno', SenderType::Agent));
        Http::assertSent(fn (HttpRequest $r) => $r['Body'] === 'Uno' && ! isset($r['StatusCallback']));

        // Con URL pública: sí, y el callback actualiza el estado a entregado
        config(['whatsapp.public_url' => self::PUBLIC_URL]);
        $message = $sender->create('50370123456', 'Dos', SenderType::Agent);
        $sender->deliver($message);
        Http::assertSent(fn (HttpRequest $r) => $r['Body'] === 'Dos' && $r['StatusCallback'] === self::PUBLIC_URL.'/api/webhooks/whatsapp/twilio');

        $params = ['MessageSid' => $message->fresh()->provider_message_id, 'MessageStatus' => 'delivered'];
        $this->post('/api/webhooks/whatsapp/twilio', $params, ['X-Twilio-Signature' => $this->sign(Webhooks::url('twilio'), $params)])->assertOk();
        $this->assertSame(MessageStatus::Delivered, $message->fresh()->status);
    }
}
