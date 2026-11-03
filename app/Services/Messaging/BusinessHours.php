<?php

namespace App\Services\Messaging;

use Carbon\CarbonImmutable;

/**
 * Horario de atención configurable (system_settings: hours.schedule).
 */
final class BusinessHours
{
    private const DAYS = ['mon' => 'Lun', 'tue' => 'Mar', 'wed' => 'Mié', 'thu' => 'Jue', 'fri' => 'Vie', 'sat' => 'Sáb', 'sun' => 'Dom'];

    public static function isOpen(?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now((string) setting('hours.timezone', config('app.timezone')));
        $schedule = (array) setting('hours.schedule', []);
        $day = strtolower($at->format('D'));
        $range = $schedule[$day] ?? null;

        if (! is_array($range) || count($range) !== 2 || ! $range[0] || ! $range[1]) {
            return false;
        }

        $time = $at->format('H:i');

        return $time >= $range[0] && $time < $range[1];
    }

    /** Texto legible: "Lun–Vie 08:00–17:00 · Sáb 08:00–12:00" */
    public static function describe(): string
    {
        $schedule = (array) setting('hours.schedule', []);
        $groups = [];

        foreach (self::DAYS as $key => $label) {
            $range = $schedule[$key] ?? null;
            $text = is_array($range) && ($range[0] ?? null) && ($range[1] ?? null) ? $range[0].'–'.$range[1] : null;
            if ($text === null) {
                continue;
            }
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['text'] === $text && $groups[$last]['end_index'] === array_search($key, array_keys(self::DAYS)) - 1) {
                $groups[$last]['end'] = $label;
                $groups[$last]['end_index']++;
            } else {
                $groups[] = ['start' => $label, 'end' => $label, 'text' => $text, 'end_index' => array_search($key, array_keys(self::DAYS))];
            }
        }

        return collect($groups)
            ->map(fn ($g) => ($g['start'] === $g['end'] ? $g['start'] : $g['start'].'–'.$g['end']).' '.$g['text'])
            ->implode(' · ') ?: 'No definido';
    }

    /** ¿La hora actual está dentro de la ventana permitida para mensajes de cobranza? */
    public static function withinCollectionWindow(?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now((string) setting('hours.timezone', config('app.timezone')));
        $from = (string) setting('communication.collection_send_from', '08:00');
        $until = (string) setting('communication.collection_send_until', '19:00');
        $time = $at->format('H:i');

        return $time >= $from && $time < $until;
    }

    /** Segundos hasta que abra la ventana de cobranza. */
    public static function secondsUntilCollectionWindow(): int
    {
        $tz = (string) setting('hours.timezone', config('app.timezone'));
        $now = CarbonImmutable::now($tz);
        [$h, $m] = array_map('intval', explode(':', (string) setting('communication.collection_send_from', '08:00')));
        $open = $now->setTime($h, $m);
        if ($open->lte($now)) {
            $open = $open->addDay();
        }

        return max(60, (int) $now->diffInSeconds($open));
    }
}
