<?php

namespace App\Services\Messaging;

use App\Enums\CampaignType;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Construye la audiencia de una campaña a partir de filtros y aplica las reglas de elegibilidad:
 *  - Todas: cliente activo, sin baja de WhatsApp y con número válido.
 *  - Cobranza: con saldo, estado de mora no solvente ni excluido y que acepte mensajes de cobranza.
 *  - Promoción: solo clientes con consentimiento explícito para promociones.
 */
class CampaignAudience
{
    public const FILTER_DEFAULTS = [
        'customer_ids' => [],
        'group_ids' => [],
        'delinquency_status_ids' => [],
        'solvent_only' => false,
        'balance_min' => null,
        'balance_max' => null,
        'days_min' => null,
        'days_max' => null,
        'registered_from' => null,
        'registered_to' => null,
    ];

    /** Clientes que cumplen los filtros (sin aplicar reglas de elegibilidad). */
    public function filtered(array $filters): Builder
    {
        $f = array_merge(self::FILTER_DEFAULTS, $filters);

        return Customer::query()
            ->when($f['customer_ids'] !== [], fn (Builder $q) => $q->whereIn('customers.id', array_map('intval', $f['customer_ids'])))
            ->when($f['group_ids'] !== [], fn (Builder $q) => $q->whereHas('groups', fn (Builder $g) => $g->whereIn('customer_groups.id', array_map('intval', $f['group_ids']))))
            ->when($f['delinquency_status_ids'] !== [], fn (Builder $q) => $q->whereIn('delinquency_status_id', array_map('intval', $f['delinquency_status_ids'])))
            ->when($f['solvent_only'], fn (Builder $q) => $q->whereHas('delinquencyStatus', fn (Builder $s) => $s->where('is_solvent', true)))
            ->when(is_numeric($f['balance_min']), fn (Builder $q) => $q->where('outstanding_balance', '>=', (float) $f['balance_min']))
            ->when(is_numeric($f['balance_max']), fn (Builder $q) => $q->where('outstanding_balance', '<=', (float) $f['balance_max']))
            ->when(is_numeric($f['days_min']), fn (Builder $q) => $q->where('days_overdue', '>=', (int) $f['days_min']))
            ->when(is_numeric($f['days_max']), fn (Builder $q) => $q->where('days_overdue', '<=', (int) $f['days_max']))
            ->when(filled($f['registered_from']), fn (Builder $q) => $q->whereDate('registered_at', '>=', $f['registered_from']))
            ->when(filled($f['registered_to']), fn (Builder $q) => $q->whereDate('registered_at', '<=', $f['registered_to']));
    }

    /** Clientes que efectivamente recibirán el mensaje. */
    public function eligible(CampaignType $type, array $filters): Builder
    {
        $query = $this->filtered($filters)
            ->where('customers.status', CustomerStatus::Active)
            ->whereNull('whatsapp_opted_out_at')
            ->whereNotNull('whatsapp_number');

        return match ($type) {
            CampaignType::Collection => $query
                ->where('accepts_collection_messages', true)
                ->where('outstanding_balance', '>', 0)
                ->whereHas('delinquencyStatus', fn (Builder $s) => $s->where('is_solvent', false)->where('exclude_from_campaigns', false)),
            CampaignType::Promotion => $query->where('accepts_promotions', true),
            default => $query,
        };
    }

    /**
     * Desglose de exclusiones para mostrar en la previsualización.
     *
     * @return array{matched:int, eligible:int, excluded: array<string,int>}
     */
    public function summary(CampaignType $type, array $filters): array
    {
        $matched = $this->filtered($filters)->count();
        $eligible = $this->eligible($type, $filters)->count();

        $excluded = [
            'Clientes inactivos' => $this->filtered($filters)->where('customers.status', CustomerStatus::Inactive)->count(),
            'Solicitaron la baja de WhatsApp' => $this->filtered($filters)->where('customers.status', CustomerStatus::Active)->whereNotNull('whatsapp_opted_out_at')->count(),
        ];

        $base = fn () => $this->filtered($filters)->where('customers.status', CustomerStatus::Active)->whereNull('whatsapp_opted_out_at');

        if ($type === CampaignType::Collection) {
            $excluded['Clientes solventes (no reciben cobranza)'] = $base()->whereHas('delinquencyStatus', fn ($s) => $s->where('is_solvent', true))->count();
            $excluded['Estado excluido de campañas'] = $base()->whereHas('delinquencyStatus', fn ($s) => $s->where('is_solvent', false)->where('exclude_from_campaigns', true))->count();
            $excluded['Sin estado de mora asignado'] = $base()->whereNull('delinquency_status_id')->count();
            $excluded['No aceptan mensajes de cobranza'] = $base()->where('accepts_collection_messages', false)->count();
        }

        if ($type === CampaignType::Promotion) {
            $excluded['Sin consentimiento para promociones'] = $base()->where('accepts_promotions', false)->count();
        }

        return ['matched' => $matched, 'eligible' => $eligible, 'excluded' => array_filter($excluded)];
    }
}
