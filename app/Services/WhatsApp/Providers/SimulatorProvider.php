<?php

namespace App\Services\WhatsApp\Providers;

use App\Enums\MessageStatus;
use App\Services\WhatsApp\Contracts\WhatsAppProvider;
use App\Services\WhatsApp\Data\InboundMessage;
use App\Services\WhatsApp\Data\SendResult;
use App\Services\WhatsApp\Data\WebhookPayload;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proveedor de SIMULACIÓN para desarrollo y pruebas. No se conecta a WhatsApp ni envía mensajes reales:
 * registra los envíos en la base de datos y permite probar campañas, conversaciones y el chatbot sin costo.
 *
 * Reglas de simulación:
 *  - Un porcentaje configurable de envíos falla aleatoriamente (whatsapp.simulator_failure_rate).
 *  - Los números que terminan en 0000 siempre fallan ("número sin WhatsApp"), útil para pruebas deterministas.
 */
class SimulatorProvider implements WhatsAppProvider
{
    public const ALWAYS_FAIL_SUFFIX = '0000';

    public function name(): string
    {
        return 'simulator';
    }

    public function label(): string
    {
        return 'Simulador (modo de pruebas)';
    }

    public function isSimulation(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function sendText(string $to, string $body): SendResult
    {
        $to = (string) Phone::normalize($to);

        if (str_ends_with($to, self::ALWAYS_FAIL_SUFFIX)) {
            return SendResult::failed('SIM_NOT_ON_WHATSAPP', 'Simulación: el número no tiene WhatsApp registrado.');
        }

        $failureRate = (int) setting('whatsapp.simulator_failure_rate', 0);
        if ($failureRate > 0 && random_int(1, 100) <= $failureRate) {
            return SendResult::failed('SIM_RANDOM_FAILURE', 'Simulación: error aleatorio de envío.', retryable: false);
        }

        return SendResult::ok('sim_'.Str::lower((string) Str::ulid()), MessageStatus::Delivered, ['simulated' => true]);
    }

    public function sendTemplate(string $to, string $templateName, string $language, array $parameters = [], ?string $fallbackBody = null): SendResult
    {
        return $this->sendText($to, $fallbackBody ?? $templateName);
    }

    public function verificationChallenge(Request $request): ?Response
    {
        return null;
    }

    /** El webhook del simulador solo se acepta fuera de producción. */
    public function validateWebhookSignature(Request $request): bool
    {
        return ! app()->isProduction();
    }

    /** Formato JSON de prueba: {"from": "50370000000", "body": "Hola", "name": "Juan"} */
    public function parseWebhook(Request $request): WebhookPayload
    {
        $from = Phone::normalize($request->string('from')->toString());
        $body = trim($request->string('body')->toString());

        if (! $from || $body === '') {
            return new WebhookPayload;
        }

        return new WebhookPayload(messages: [
            new InboundMessage(
                from: $from,
                body: $body,
                providerMessageId: 'sim_in_'.Str::lower((string) Str::ulid()),
                contactName: $request->string('name')->toString() ?: null,
                receivedAt: CarbonImmutable::now(),
            ),
        ]);
    }
}
