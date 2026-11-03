<?php

namespace App\Support;

final class Money
{
    public static function format(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $amount, 2, '.', ',');

        return $withSymbol ? '$'.$formatted : $formatted;
    }

    public static function compact(float|int|string|null $amount): string
    {
        $value = (float) $amount;

        return match (true) {
            $value >= 1_000_000 => '$'.number_format($value / 1_000_000, 1).'M',
            $value >= 10_000 => '$'.number_format($value / 1_000, 1).'K',
            default => self::format($value),
        };
    }
}
