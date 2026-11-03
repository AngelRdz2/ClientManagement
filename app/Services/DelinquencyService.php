<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\StatusChangeSource;
use App\Models\Customer;
use App\Models\CustomerStatusHistory;
use App\Models\DelinquencyStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio de estados de mora: asignación, cambios con historial y recálculo automático.
 */
class DelinquencyService
{
    /** @var Collection<int, DelinquencyStatus>|null */
    private ?Collection $assignable = null;

    /**
     * Estado que corresponde a una cantidad de días de mora, considerando solo estados activos
     * y asignables automáticamente (el de mayor umbral que no supere los días).
     */
    public function statusForDays(int $days): ?DelinquencyStatus
    {
        $this->assignable ??= DelinquencyStatus::query()
            ->active()
            ->where('is_auto_assignable', true)
            ->orderByDesc('min_days_overdue')
            ->orderBy('priority')
            ->get();

        if ($days <= 0) {
            return $this->assignable->firstWhere('is_solvent', true)
                ?? $this->assignable->firstWhere('min_days_overdue', 0);
        }

        return $this->assignable->first(fn (DelinquencyStatus $s) => $s->min_days_overdue <= $days && ! $s->is_solvent)
            ?? $this->assignable->firstWhere('is_solvent', true);
    }

    /**
     * Cambia el estado de mora de un cliente registrando el historial (que nunca se sobrescribe).
     * Devuelve true si hubo un cambio de estado.
     */
    public function changeStatus(
        Customer $customer,
        ?DelinquencyStatus $to,
        ?int $daysOverdue,
        StatusChangeSource $source,
        ?string $reason = null,
        ?User $user = null,
    ): bool {
        $fromId = $customer->delinquency_status_id;
        $fromDays = $customer->days_overdue;
        $newDays = $daysOverdue ?? $fromDays;
        $statusChanged = $fromId !== $to?->id;

        if (! $statusChanged && $fromDays === $newDays) {
            return false;
        }

        DB::transaction(function () use ($customer, $to, $fromId, $fromDays, $newDays, $statusChanged, $source, $reason, $user) {
            $customer->forceFill([
                'delinquency_status_id' => $to?->id,
                'days_overdue' => $newDays,
            ])->save();

            if ($statusChanged) {
                CustomerStatusHistory::create([
                    'customer_id' => $customer->id,
                    'from_status_id' => $fromId,
                    'to_status_id' => $to?->id,
                    'from_days_overdue' => $fromDays,
                    'to_days_overdue' => $newDays,
                    'source' => $source,
                    'reason' => $reason,
                    'user_id' => $user?->id,
                ]);
            }
        });

        if ($statusChanged && $source === StatusChangeSource::Manual) {
            $customer->setRelation('delinquencyStatus', $to);
            ActivityLogger::log(
                'status.changed',
                "Estado de mora de {$customer->full_name} cambiado a ".($to?->name ?? 'Sin estado'),
                $customer,
                ['from' => $fromId, 'to' => $to?->id, 'reason' => $reason],
                $user?->id,
            );
        }

        return $statusChanged;
    }

    /** Días de mora de un cliente según la fecha de pago más atrasada de sus artículos con saldo. */
    public function daysOverdueFromArticles(Customer $customer): int
    {
        $today = now()->startOfDay();

        return (int) $customer->articles()
            ->whereIn('status', [ArticleStatus::Active, ArticleStatus::InCollection])
            ->where('balance', '>', 0)
            ->whereNotNull('next_payment_date')
            ->get(['id', 'next_payment_date'])
            ->map(fn ($a) => $a->next_payment_date->lt($today) ? (int) $a->next_payment_date->diffInDays($today) : 0)
            ->max();
    }

    /**
     * Recalcula días y estado de mora a partir de las fechas de pago de los artículos.
     * Respeta los estados manuales (no asignables automáticamente): no los sobrescribe.
     */
    public function recalculate(Customer $customer, ?User $user = null): bool
    {
        $customer->loadMissing('delinquencyStatus');

        if ($customer->delinquencyStatus && ! $customer->delinquencyStatus->is_auto_assignable) {
            return false;
        }

        $days = $this->daysOverdueFromArticles($customer);
        $this->updateArticleDays($customer);

        return $this->changeStatus(
            $customer,
            $this->statusForDays($days),
            $days,
            StatusChangeSource::Automatic,
            'Recálculo automático por fechas de pago',
            $user,
        );
    }

    /** Actualiza los días de mora de cada artículo según su próxima fecha de pago. */
    public function updateArticleDays(Customer $customer): void
    {
        $today = now()->startOfDay();
        foreach ($customer->articles()->get() as $article) {
            $days = ($article->next_payment_date && $article->next_payment_date->lt($today) && (float) $article->balance > 0)
                ? (int) $article->next_payment_date->diffInDays($today)
                : 0;
            if ($article->days_overdue !== $days) {
                $article->forceFill(['days_overdue' => $days])->saveQuietly();
            }
        }
    }

    public function flushCache(): void
    {
        $this->assignable = null;
    }
}
