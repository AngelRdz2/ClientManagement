<?php

namespace App\Http\Controllers;

use App\Services\Conversations\InboundMessageService;
use App\Services\Conversations\StatusUpdateService;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Webhooks de proveedores de WhatsApp.
 *  GET  /api/webhooks/whatsapp/meta    → verificación de suscripción (hub.challenge)
 *  POST /api/webhooks/whatsapp/{meta|twilio|simulator} → mensajes entrantes y estados de entrega
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private WhatsAppManager $whatsapp,
        private InboundMessageService $inbound,
        private StatusUpdateService $statuses,
    ) {}

    public function verify(Request $request, string $provider): Response
    {
        $response = $this->resolve($provider)->verificationChallenge($request);

        return $response ?? response('Not found', 404);
    }

    public function receive(Request $request, string $provider): Response
    {
        $driver = $this->resolve($provider);

        if (! $driver->validateWebhookSignature($request)) {
            Log::warning('Webhook de WhatsApp rechazado: firma inválida', ['provider' => $provider, 'ip' => $request->ip()]);

            return response('Firma inválida', 403);
        }

        $payload = $driver->parseWebhook($request);

        foreach ($payload->messages as $message) {
            try {
                $this->inbound->receive($message, $driver->name(), simulated: $driver->isSimulation());
            } catch (Throwable $e) {
                report($e);
            }
        }

        foreach ($payload->statuses as $status) {
            try {
                $this->statuses->apply($status);
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Twilio espera TwiML; una respuesta vacía indica "sin respuesta automática" (el bot responde por la API).
        if ($driver->name() === 'twilio') {
            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
        }

        return response()->json(['received' => count($payload->messages), 'statuses' => count($payload->statuses)]);
    }

    private function resolve(string $provider)
    {
        abort_unless(isset(WhatsAppManager::PROVIDERS[$provider]), 404);

        return $this->whatsapp->provider($provider);
    }
}
