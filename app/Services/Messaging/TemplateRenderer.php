<?php

namespace App\Services\Messaging;

use App\Enums\ArticleStatus;
use App\Models\Customer;
use App\Models\CustomerArticle;
use App\Support\Dui;
use App\Support\Phone;
use Illuminate\Support\Collection;

/**
 * Reemplaza variables {{variable}} de las plantillas con datos reales del cliente y sus artículos.
 */
class TemplateRenderer
{
    /** variable => descripción (se muestra en el editor de plantillas) */
    public const VARIABLES = [
        'Cliente' => [
            'nombre' => 'Nombre completo',
            'primer_nombre' => 'Primer nombre',
            'dui' => 'DUI completo',
            'dui_parcial' => 'DUI parcialmente oculto',
            'codigo_cliente' => 'Código de cliente',
            'telefono' => 'Teléfono principal',
            'estado_mora' => 'Estado de mora',
            'dias_mora' => 'Días de mora',
        ],
        'Cuenta' => [
            'saldo_pendiente' => 'Saldo total pendiente (sin $)',
            'valor_cuota' => 'Cuota mensual total (sin $)',
            'cuotas_pendientes' => 'Total de cuotas pendientes',
            'fecha_pago' => 'Próxima fecha de pago (dd/mm/aaaa)',
            'cantidad_articulos' => 'Artículos con saldo',
            'lista_articulos' => 'Lista de artículos con saldo y cuotas',
        ],
        'Artículo principal' => [
            'articulo_descripcion' => 'Descripción del artículo más próximo a vencer',
            'articulo_codigo' => 'Código del artículo',
            'articulo_contrato' => 'Número de contrato',
            'articulo_saldo' => 'Saldo del artículo (sin $)',
            'articulo_cuota' => 'Valor de cuota del artículo (sin $)',
            'articulo_cuotas_pendientes' => 'Cuotas pendientes del artículo',
            'articulo_fecha_pago' => 'Próxima fecha de pago del artículo',
        ],
        'Empresa' => [
            'empresa' => 'Nombre de la empresa',
            'telefono_empresa' => 'Teléfono de la empresa',
            'correo_empresa' => 'Correo de la empresa',
            'direccion_empresa' => 'Dirección de la empresa',
            'horario' => 'Horario de atención',
            'fecha_actual' => 'Fecha de hoy',
        ],
    ];

    public function render(string $body, ?Customer $customer = null, ?CustomerArticle $article = null, array $extra = []): string
    {
        $values = $this->values($customer, $article) + $extra;

        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($values) {
            $key = strtolower($m[1]);

            return array_key_exists($key, $values) ? (string) $values[$key] : $m[0];
        }, $body);
    }

    /** @return list<string> variables usadas en orden de aparición (para plantillas aprobadas de Meta: {{1}}, {{2}}...) */
    public function variablesIn(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        return array_map('strtolower', $matches[1]);
    }

    /** @return list<string> variables que no existen en el catálogo */
    public function unknownVariables(string $body): array
    {
        $known = array_merge(...array_values(array_map('array_keys', self::VARIABLES)));

        return array_values(array_unique(array_diff($this->variablesIn($body), $known)));
    }

    /** @return list<string> valores de las variables en orden de aparición */
    public function orderedValues(string $body, ?Customer $customer = null): array
    {
        $values = $this->values($customer);

        return array_map(fn ($var) => (string) ($values[$var] ?? ''), $this->variablesIn($body));
    }

    /** @return array<string, string> */
    public function values(?Customer $customer = null, ?CustomerArticle $article = null): array
    {
        $values = [
            'empresa' => (string) setting('company.name'),
            'telefono_empresa' => (string) setting('company.phone'),
            'correo_empresa' => (string) setting('company.email'),
            'direccion_empresa' => (string) setting('company.address'),
            'horario' => BusinessHours::describe(),
            'fecha_actual' => now()->format('d/m/Y'),
        ];

        if (! $customer) {
            return $values;
        }

        $customer->loadMissing(['articles', 'delinquencyStatus']);
        /** @var Collection<int, CustomerArticle> $open */
        $open = $customer->articles
            ->filter(fn (CustomerArticle $a) => (float) $a->balance > 0 && in_array($a->status, [ArticleStatus::Active, ArticleStatus::InCollection], true))
            ->sortBy(fn (CustomerArticle $a) => $a->next_payment_date?->timestamp ?? PHP_INT_MAX)
            ->values();
        $main = $article ?? $open->first();

        $list = $open->values()->map(fn (CustomerArticle $a, int $i) => sprintf(
            "%d. %s\n   Saldo: $%s · Cuotas pendientes: %d%s",
            $i + 1, $a->description, number_format((float) $a->balance, 2), $a->installments_pending,
            $a->next_payment_date ? ' · Próximo pago: '.$a->next_payment_date->format('d/m/Y') : '',
        ))->implode("\n");

        return $values + [
            'nombre' => $customer->full_name,
            'primer_nombre' => strtok($customer->full_name, ' ') ?: $customer->full_name,
            'dui' => (string) $customer->dui,
            'dui_parcial' => Dui::mask($customer->dui),
            'codigo_cliente' => $customer->code,
            'telefono' => Phone::format($customer->phone_primary),
            'estado_mora' => $customer->delinquencyStatus?->name ?? 'Sin estado',
            'dias_mora' => (string) $customer->days_overdue,
            'saldo_pendiente' => number_format((float) $customer->outstanding_balance, 2),
            'valor_cuota' => number_format($open->sum(fn ($a) => (float) $a->installment_amount), 2),
            'cuotas_pendientes' => (string) $open->sum('installments_pending'),
            'fecha_pago' => $customer->next_payment_date?->format('d/m/Y') ?? '—',
            'cantidad_articulos' => (string) $open->count(),
            'lista_articulos' => $list !== '' ? $list : 'Sin artículos con saldo pendiente.',
            'articulo_descripcion' => $main?->description ?? '—',
            'articulo_codigo' => $main?->article_code ?? '—',
            'articulo_contrato' => $main?->contract_number ?? '—',
            'articulo_saldo' => number_format((float) ($main?->balance ?? 0), 2),
            'articulo_cuota' => number_format((float) ($main?->installment_amount ?? 0), 2),
            'articulo_cuotas_pendientes' => (string) ($main?->installments_pending ?? 0),
            'articulo_fecha_pago' => $main?->next_payment_date?->format('d/m/Y') ?? '—',
        ];
    }
}
