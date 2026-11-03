<?php

use App\Services\Settings;
use App\Support\Money;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('wa_format')) {
    /** Convierte el formato de WhatsApp (*negrita*, _cursiva_, ~tachado~) a HTML seguro. */
    function wa_format(?string $text, bool $highlightVariables = false): string
    {
        $html = e((string) $text);
        $html = preg_replace('/\*(\S(?:.*?\S)?)\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<![\w])_(\S(?:.*?\S)?)_(?![\w])/u', '<em>$1</em>', $html);
        $html = preg_replace('/~(\S(?:.*?\S)?)~/u', '<s>$1</s>', $html);
        if ($highlightVariables) {
            $html = preg_replace('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', '<span class="rounded bg-indigo-100 px-1 font-mono text-[0.85em] text-indigo-700">{{$1}}</span>', $html);
        }

        return nl2br($html, false);
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $amount): string
    {
        return Money::format($amount);
    }
}
