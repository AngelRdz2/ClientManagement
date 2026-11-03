<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotFlow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Detección de intenciones por reglas: palabras clave administrables (tabla chatbot_flows), ordenadas por prioridad.
 * Las opciones numéricas del menú ("1", "2"...) solo coinciden si el mensaje es exactamente ese número.
 */
class IntentDetector
{
    /** @var Collection<int, ChatbotFlow>|null */
    private ?Collection $flows = null;

    public static function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    public function detect(string $text): ?ChatbotFlow
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($this->flows() as $flow) {
            foreach ((array) $flow->keywords as $keyword) {
                $kw = self::normalize((string) $keyword);
                if ($kw === '') {
                    continue;
                }

                $matches = ctype_digit($kw)
                    ? $normalized === $kw
                    : preg_match('/(^|\s)'.preg_quote($kw, '/').'(\s|$)/', $normalized) === 1;

                if ($matches) {
                    // Prioridad del flujo + especificidad (frases más largas pesan más).
                    $score = $flow->priority * 100 + strlen($kw);
                    if ($score > $bestScore) {
                        $best = $flow;
                        $bestScore = $score;
                    }
                }
            }
        }

        return $best;
    }

    public function flow(string $intent): ?ChatbotFlow
    {
        return $this->flows()->firstWhere('intent', $intent);
    }

    /** @return Collection<int, ChatbotFlow> */
    private function flows(): Collection
    {
        return $this->flows ??= ChatbotFlow::query()->active()->orderByDesc('priority')->get();
    }
}
