<?php

namespace App\Support;

/**
 * Normaliza números telefónicos al formato usado por WhatsApp: solo dígitos con código de país.
 */
final class Phone
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }

        $countryCode = (string) config('crm.default_country_code', '503');
        $localLength = (int) config('crm.local_phone_length', 8);

        if (strlen($digits) === $localLength) {
            return $countryCode.$digits;
        }

        return ltrim($digits, '0');
    }

    /** Formato legible: +503 7000-0000 */
    public static function format(?string $phone): string
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return '';
        }

        $countryCode = (string) config('crm.default_country_code', '503');
        if (str_starts_with($normalized, $countryCode) && strlen($normalized) === strlen($countryCode) + 8) {
            $local = substr($normalized, strlen($countryCode));

            return '+'.$countryCode.' '.substr($local, 0, 4).'-'.substr($local, 4);
        }

        return '+'.$normalized;
    }

    /** Oculta parcialmente el número: +503 ****-1234 */
    public static function mask(?string $phone): string
    {
        $normalized = self::normalize($phone);

        return $normalized ? '****-'.substr($normalized, -4) : '';
    }
}
