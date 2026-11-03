<?php

namespace App\Support;

/**
 * URL de los webhooks de WhatsApp tal como las ve el proveedor desde Internet.
 */
final class Webhooks
{
    /** URL pública del webhook de un proveedor (usa WHATSAPP_PUBLIC_URL si está definida). */
    public static function url(string $provider): string
    {
        $base = rtrim((string) config('whatsapp.public_url'), '/');

        return $base !== ''
            ? $base.'/api/webhooks/whatsapp/'.$provider
            : route('webhooks.whatsapp', $provider);
    }

    /** ¿La URL es alcanzable por un proveedor externo? (HTTPS y no un dominio local). */
    public static function isPublic(?string $url = null): bool
    {
        $url ??= self::url('twilio');
        $host = (string) parse_url($url, PHP_URL_HOST);

        return str_starts_with($url, 'https://')
            && ! preg_match('/(\.test|\.local|\.localhost)$|^(localhost|127\.0\.0\.1)$/i', $host);
    }
}
