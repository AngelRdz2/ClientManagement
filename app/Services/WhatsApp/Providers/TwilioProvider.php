<?php

namespace App\Services\WhatsApp\Providers;

use App\Enums\MessageStatus;
use App\Services\WhatsApp\Contracts\WhatsAppProvider;
use App\Services\WhatsApp\Data\InboundMessage;
use App\Services\WhatsApp\Data\SendResult;
use App\Services\WhatsApp\Data\StatusUpdate;
use App\Services\WhatsApp\Data\WebhookPayload;
use App\Support\Phone;
use App\Support\Webhooks;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Twilio WhatsApp.
 *
 * Credenciales: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM (ej. +14155238886)
 * Webhook (mensajes entrantes y StatusCallback): POST {APP_URL}/webhooks/whatsapp/twilio
 */
class TwilioProvider implements WhatsAppProvider
{
    public function name(): string
    {
        return 'twilio';
    }

    public function label(): string
    {
        return 'Twilio WhatsApp';
    }

    public function isSimulation(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        return filled($this->sid()) && filled($this->token()) && filled($this->from());
    }

    public function sendText(string $to, string $body): SendResult
    {
        return $this->post(['Body' => $body], $to);
    }

    /**
     * En Twilio las plantillas aprobadas se envían con un Content SID (HX...). Si el nombre no es un
     * Content SID se envía el texto de respaldo.
     */
    public function sendTemplate(string $to, string $templateName, string $language, array $parameters = [], ?string $fallbackBody = null): SendResult
    {
        if (str_starts_with($templateName, 'HX')) {
            $variables = [];
            foreach (array_values($parameters) as $i => $value) {
                $variables[(string) ($i + 1)] = (string) $value;
            }

            return $this->post(['ContentSid' => $templateName, 'ContentVariables' => json_encode($variables)], $to);
        }

        return $this->sendText($to, $fallbackBody ?? $templateName);
    }

    public function verificationChallenge(Request $request): ?Response
    {
        return null;
    }

    /** Valida X-Twilio-Signature: HMAC-SHA1 de la URL + parámetros POST ordenados, en base64. */
    public function validateWebhookSignature(Request $request): bool
    {
        $token = (string) $this->token();
        $signature = (string) $request->header('X-Twilio-Signature');

        if ($token === '' || $signature === '') {
            return false;
        }

        $params = $request->post();
        ksort($params);
        $payload = '';
        foreach ($params as $key => $value) {
            $payload .= $key.(is_array($value) ? implode('', $value) : $value);
        }

        // Twilio firma la URL PÚBLICA a la que envió el webhook. Detrás de un túnel o proxy, Laravel ve otra URL
        // (ej. http://client-management.test), por eso se prueba también con la URL pública configurada.
        $query = $request->getQueryString();
        $candidates = array_unique([
            $request->fullUrl(),
            Webhooks::url('twilio').($query ? '?'.$query : ''),
        ]);

        foreach ($candidates as $url) {
            if (hash_equals(base64_encode(hash_hmac('sha1', $url.$payload, $token, true)), $signature)) {
                return true;
            }
        }

        Log::warning('Twilio: firma de webhook no coincide con ninguna URL esperada', ['urls' => array_values($candidates)]);

        return false;
    }

    public function parseWebhook(Request $request): WebhookPayload
    {
        $sid = (string) $request->input('MessageSid', $request->input('SmsSid', ''));
        $body = $request->input('Body');
        $from = (string) $request->input('From', '');

        // Mensaje entrante
        if ($body !== null && str_starts_with($from, 'whatsapp:') && $request->input('SmsStatus', 'received') === 'received') {
            return new WebhookPayload(messages: [
                new InboundMessage(
                    from: (string) Phone::normalize(str_replace('whatsapp:', '', $from)),
                    body: (string) $body,
                    providerMessageId: $sid ?: null,
                    contactName: $request->input('ProfileName'),
                    receivedAt: CarbonImmutable::now(),
                ),
            ]);
        }

        // Actualización de estado (StatusCallback)
        $status = match ((string) $request->input('MessageStatus', '')) {
            'sent' => MessageStatus::Sent,
            'delivered' => MessageStatus::Delivered,
            'read' => MessageStatus::Read,
            'failed', 'undelivered' => MessageStatus::Failed,
            default => null,
        };

        if ($status === null || $sid === '') {
            return new WebhookPayload;
        }

        return new WebhookPayload(statuses: [
            new StatusUpdate(
                providerMessageId: $sid,
                status: $status,
                occurredAt: CarbonImmutable::now(),
                errorCode: $request->input('ErrorCode') ? (string) $request->input('ErrorCode') : null,
                errorMessage: $request->input('ErrorMessage'),
            ),
        ]);
    }

    /** @param array<string, string> $params */
    private function post(array $params, string $to): SendResult
    {
        if (! $this->isConfigured()) {
            return SendResult::failed('NOT_CONFIGURED', 'Twilio no está configurado (faltan credenciales).');
        }

        $url = sprintf('%s/Accounts/%s/Messages.json', rtrim(config('whatsapp.twilio.api_url'), '/'), $this->sid());
        $params += [
            'From' => 'whatsapp:+'.Phone::normalize($this->from()),
            'To' => 'whatsapp:+'.Phone::normalize($to),
        ];
        // Estados de entrega/lectura: solo si Twilio puede alcanzar la URL (HTTPS pública).
        if (Webhooks::isPublic($callback = Webhooks::url('twilio'))) {
            $params['StatusCallback'] = $callback;
        }

        try {
            $response = Http::asForm()->withBasicAuth($this->sid(), $this->token())->timeout(20)->post($url, $params);
        } catch (ConnectionException $e) {
            return SendResult::failed('CONNECTION_ERROR', 'No fue posible conectar con Twilio: '.$e->getMessage(), retryable: true);
        }

        $json = $response->json() ?? [];
        if ($response->successful() && isset($json['sid'])) {
            return SendResult::ok($json['sid'], MessageStatus::Queued, $json);
        }

        $code = (string) ($json['code'] ?? $response->status());
        Log::warning('WhatsApp Twilio: error de envío', ['code' => $code, 'message' => $json['message'] ?? null]);

        return SendResult::failed($code, $json['message'] ?? 'Error desconocido de Twilio', $response->status() === 429 || $response->serverError(), $json);
    }

    private function sid(): ?string
    {
        return setting('whatsapp.twilio_account_sid');
    }

    private function token(): ?string
    {
        return setting('whatsapp.twilio_auth_token');
    }

    private function from(): ?string
    {
        return setting('whatsapp.twilio_from_number');
    }
}
