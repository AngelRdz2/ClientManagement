<?php

namespace App\Support;

/**
 * Documento Único de Identidad (El Salvador): 8 dígitos + dígito verificador (00000000-0).
 */
final class Dui
{
    /** Normaliza a formato 00000000-0. Devuelve null si no tiene 9 dígitos. */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        if (strlen($digits) !== 9) {
            return null;
        }

        return substr($digits, 0, 8).'-'.substr($digits, 8, 1);
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);
        if ($normalized === null) {
            return false;
        }

        if (! config('crm.validate_dui_checksum', true)) {
            return true;
        }

        $digits = str_replace('-', '', $normalized);

        return (int) $digits[8] === self::checkDigit(substr($digits, 0, 8));
    }

    /** Algoritmo del dígito verificador: suma ponderada (9..2) módulo 10. */
    public static function checkDigit(string $eightDigits): int
    {
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += (int) $eightDigits[$i] * (9 - $i);
        }

        $check = 10 - ($sum % 10);

        return $check === 10 ? 0 : $check;
    }

    /** Genera un DUI válido a partir de 8 dígitos (útil para datos de demostración). */
    public static function fromBase(string $eightDigits): string
    {
        return $eightDigits.'-'.self::checkDigit($eightDigits);
    }

    /** Oculta el DUI: *****567-8 */
    public static function mask(?string $value): string
    {
        $normalized = self::normalize($value);

        return $normalized ? '*****'.substr($normalized, 5) : '';
    }
}
