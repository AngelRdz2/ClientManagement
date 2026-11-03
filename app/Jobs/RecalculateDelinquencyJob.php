<?php

namespace App\Jobs;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DelinquencyService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalcula días y estado de mora de todos los clientes activos según las fechas de pago de sus artículos.
 * Los estados de asignación manual (ej. "Última gestión", "Recuperación") se respetan.
 */
class RecalculateDelinquencyJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public ?int $userId = null) {}

    public function handle(DelinquencyService $service): void
    {
        $service->flushCache();
        $user = $this->userId ? User::find($this->userId) : null;
        $changed = 0;
        $processed = 0;

        Customer::query()
            ->where('status', CustomerStatus::Active)
            ->with('delinquencyStatus')
            ->chunkById(200, function ($customers) use ($service, $user, &$changed, &$processed) {
                foreach ($customers as $customer) {
                    $processed++;
                    if ($service->recalculate($customer, $user)) {
                        $changed++;
                    }
                }
            });

        ActivityLogger::log('status.recalculated', "Recálculo automático de mora: {$processed} clientes revisados, {$changed} cambios de estado", null, compact('processed', 'changed'), $this->userId);
    }
}
