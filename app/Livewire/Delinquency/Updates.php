<?php

namespace App\Livewire\Delinquency;

use App\Enums\StatusChangeSource;
use App\Jobs\RecalculateDelinquencyJob;
use App\Models\Customer;
use App\Models\DelinquencyStatus;
use App\Services\ActivityLogger;
use App\Services\DelinquencyService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mantenimiento de estados: actualización manual individual o masiva y recálculo automático por fechas de pago.
 */
#[Title('Actualizar estados de mora')]
class Updates extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $currentStatus = '';

    public string $daysMin = '';

    public string $daysMax = '';

    /** @var list<string> */
    public array $selected = [];

    public bool $selectAllMatching = false;

    public string $targetStatus = '';

    public string $targetDays = '';

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('customers.manage');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'currentStatus', 'daysMin', 'daysMax'], true)) {
            $this->resetPage();
            $this->selected = [];
            $this->selectAllMatching = false;
        }
    }

    protected function query(): Builder
    {
        return Customer::query()
            ->active()
            ->search($this->search)
            ->when($this->currentStatus === 'none', fn ($q) => $q->whereNull('delinquency_status_id'))
            ->when(is_numeric($this->currentStatus), fn ($q) => $q->where('delinquency_status_id', (int) $this->currentStatus))
            ->when(is_numeric($this->daysMin), fn ($q) => $q->where('days_overdue', '>=', (int) $this->daysMin))
            ->when(is_numeric($this->daysMax), fn ($q) => $q->where('days_overdue', '<=', (int) $this->daysMax));
    }

    public function apply(DelinquencyService $service): void
    {
        $this->validate([
            'targetStatus' => ['required', 'exists:delinquency_statuses,id'],
            'targetDays' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: ['targetStatus' => 'nuevo estado', 'targetDays' => 'días de mora', 'reason' => 'motivo']);

        if (! $this->selectAllMatching && $this->selected === []) {
            $this->dispatch('alert', type: 'warning', title: 'Sin selección', message: 'Selecciona al menos un cliente.');

            return;
        }

        $status = DelinquencyStatus::findOrFail($this->targetStatus);
        $days = $this->targetDays === '' ? null : (int) $this->targetDays;
        $query = $this->selectAllMatching ? $this->query() : Customer::query()->whereIn('id', array_map('intval', $this->selected));

        $changed = 0;
        $total = 0;
        $query->chunkById(200, function ($customers) use ($service, $status, $days, &$changed, &$total) {
            foreach ($customers as $customer) {
                $total++;
                if ($service->changeStatus($customer, $status, $days, StatusChangeSource::Manual, $this->reason, auth()->user())) {
                    $changed++;
                }
            }
        });

        ActivityLogger::log('status.bulk_update', "Actualización manual de estado a “{$status->name}”: {$changed} de {$total} clientes cambiaron", null, ['status_id' => $status->id, 'reason' => $this->reason]);

        $this->reset('selected', 'selectAllMatching', 'reason', 'targetDays');
        $this->dispatch('toast', type: 'success', message: "Estado aplicado: {$changed} cambios de {$total} clientes");
    }

    public function recalculateAll(): void
    {
        RecalculateDelinquencyJob::dispatch(auth()->id());
        $this->dispatch('alert', type: 'success', title: 'Recálculo en proceso',
            message: 'El recálculo se ejecuta en segundo plano (cola). El resultado quedará en la actividad del dashboard y en el historial.');
    }

    public function render()
    {
        $customers = $this->query()->with('delinquencyStatus:id,name,color')->orderByDesc('days_overdue')->paginate(20);

        return view('livewire.delinquency.updates', [
            'customers' => $customers,
            'matching' => $this->query()->count(),
            'statuses' => DelinquencyStatus::query()->ordered()->get(['id', 'name', 'color', 'is_active', 'is_auto_assignable']),
            'pageIds' => $customers->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ]);
    }
}
