<?php

namespace App\Services\WhatsApp\Providers;

use App\Enums\MessageStatus;
use App\Services\WhatsApp\Contracts\WhatsAppProvider;
use App\Services\WhatsApp\Data\InboundMessage;
use App\Services\WhatsApp\Data\SendResult;
use App\Services\WhatsApp\Data\StatusUpdate;
use App\Services\WhatsApp\Data\WebhookPayload;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * WhatsApp Cloud API (Meta).
 *
 * Credenciales (variables de entorno o configuración cifrada):
 *  WHATSAPP_META_PHONE_NUMBER_ID, WHATSAPP_META_ACCESS_TOKEN, WHATSAPP_META_APP_SECRET, WHATSAPP_META_VERIFY_TOKEN
 *
 * Webhook: GET/POST {APP_URL}/webhooks/whatsapp/meta
 */
class MetaCloudProvider implements WhatsAppProvider
{
    /** Códigos de error de Meta que conviene reintentar (límites de tasa / errores temporales). */
    private const RETRYABLE_CODES = ['4', '80007', '130429', '131000', '131016', '131048', '131056', '133004'];

    public function name(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'WhatsApp Cloud API (Meta)';
    }

    public function isSimulation(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        return filled($this->phoneNumberId()) && filled($this->accessToken());
    }

    public function sendText(string $to, string $body): SendResult
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => Phone::normalize($to),
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    public function sendTemplate(string $to, string $templateName, string $language, array $parameters = [], ?string $fallbackBody = null): SendResult
    {
        $template = ['name' => $templateName, 'language' => ['code' => $language ?: 'es']];

        if ($parameters !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => (string) $value], array_values($parameters)),
            ]];
        }

        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => Phone::normalize($to),
            'type' => 'template',
            'template' => $template,
        ]);
    }

    public function verificationChallenge(Request $request): ?Response
    {
        if ($request->query('hub_mode') !== 'subscribe') {
            return null;
        }

        $expected = (string) setting('whatsapp.meta_verify_token');
        $given = (string) $request->query('hub_verify_token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response('Token de verificación inválido', 403);
        }

        return response((string) $request->query('hub_challenge'), 200);
    }

    public function validateWebhookSignature(Request $request): bool
    {
        $secret = (string) setting('whatsapp.meta_app_secret');
        $header = (string) $request->header('X-Hub-Signature-256');

        if ($secret === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    public function parseWebhook(Request $request): WebhookPayload
    {
        $messages = [];
        $statuses = [];

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [$c['wa_id'] ?? '' => $c['profile']['name'] ?? null]);

                foreach ((array) ($value['messages'] ?? []) as $msg) {
                    $body = match ($msg['type'] ?? 'text') {
                        'text' => $msg['text']['body'] ?? '',
                        'button' => $msg['button']['text'] ?? '',
                        'interactive' => $msg['interactive']['button_reply']['title'] ?? $msg['interactive']['list_reply']['title'] ?? '',
                        default => '['.($msg['type'] ?? 'mensaje').' no soportado]',
                    };

                    $messages[] = new InboundMessage(
                        from: (string) Phone::normalize($msg['from'] ?? ''),
                        body: $body,
                        providerMessageId: $msg['id'] ?? null,
                        contactName: $names[$msg['from'] ?? ''] ?? null,
                        receivedAt: isset($msg['timestamp']) ? CarbonImmutable::createFromTimestamp((int) $msg['timestamp']) : null,
                        type: $msg['type'] ?? 'text',
                    );
                }

                foreach ((array) ($value['statuses'] ?? []) as $st) {
                    $status = match ($st['status'] ?? '') {
                        'sent' => MessageStatus::Sent,
                        'delivered' => MessageStatus::Delivered,
                        'read' => MessageStatus::Read,
                        'failed' => MessageStatus::Failed,
                        default => null,
                    };
                    if ($status === null || empty($st['id'])) {
                        continue;
                    }

                    $error = $st['errors'][0] ?? null;
                    $statuses[] = new StatusUpdate(
                        providerMessageId: $st['id'],
                        status: $status,
                        occurredAt: isset($st['timestamp']) ? CarbonImmutable::createFromTimestamp((int) $st['timestamp']) : null,
                        errorCode: isset($error['code']) ? (string) $error['code'] : null,
                        errorMessage: $error['message'] ?? $error['title'] ?? null,
                    );
                }
            }
        }

        return new WebhookPayload($messages, $statuses);
    }

    /** @param array<string, mixed> $payload */
    private function post(array $payload): SendResult
    {
        if (! $this->isConfigured()) {
            return SendResult::failed('NOT_CONFIGURED', 'WhatsApp Cloud API no está configurada (faltan credenciales).');
        }

        $url = sprintf('%s/%s/%s/messages', rtrim(config('whatsapp.meta.graph_url'), '/'), config('whatsapp.meta.api_version'), $this->phoneNumberId());

        try {
            $response = Http::withToken($this->accessToken())->acceptJson()->timeout(20)->post($url, $payload);
        } catch (ConnectionException $e) {
            return SendResult::failed('CONNECTION_ERROR', 'No fue posible conectar con Meta: '.$e->getMessage(), retryable: true);
        }

        return $this->toResult($response);
    }

    private function toResult(HttpResponse $response): SendResult
    {
        $json = $response->json() ?? [];

        if ($response->successful() && isset($json['messages'][0]['id'])) {
            return SendResult::ok($json['messages'][0]['id'], MessageStatus::Sent, $json);
        }

        $code = (string) ($json['error']['code'] ?? $response->status());
        $message = $json['error']['error_data']['details'] ?? $json['error']['message'] ?? 'Error desconocido de Meta';
        $retryable = $response->serverError() || in_array($code, self::RETRYABLE_CODES, true);

        Log::warning('WhatsApp Meta: error de envío', ['code' => $code, 'message' => $message]);

        return SendResult::failed($code, $message, $retryable, $json);
    }

    private function phoneNumberId(): ?string
    {
        return setting('whatsapp.meta_phone_number_id');
    }

    private function accessToken(): ?string
    {
        return setting('whatsapp.meta_access_token');
    }
}
