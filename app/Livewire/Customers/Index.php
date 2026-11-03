<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\DelinquencyStatus;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clientes')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $delinquency = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $balanceMin = '';

    #[Url(except: '')]
    public string $balanceMax = '';

    #[Url(except: '')]
    public string $consent = '';

    #[Url(except: 'full_name')]
    public string $sortBy = 'full_name';

    #[Url(except: 'asc')]
    public string $sortDir = 'asc';

    public int $perPage = 15;

    private const SORTABLE = ['full_name', 'code', 'outstanding_balance', 'days_overdue', 'next_payment_date', 'registered_at'];

    public function mount(): void
    {
        $this->authorize('customers.view');
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }
        $this->sortDir = $this->sortBy === $column && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'delinquency', 'balanceMin', 'balanceMax', 'consent');
        $this->status = 'active';
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('customers.manage');
        $customer = Customer::findOrFail($id);
        $newStatus = $customer->isActive() ? CustomerStatus::Inactive : CustomerStatus::Active;
        $customer->update(['status' => $newStatus, 'updated_by' => auth()->id()]);

        ActivityLogger::log(
            $newStatus === CustomerStatus::Active ? 'customer.activated' : 'customer.deactivated',
            ($newStatus === CustomerStatus::Active ? 'Reactivó' : 'Desactivó')." al cliente {$customer->full_name}",
            $customer,
        );

        $this->dispatch('toast', type: 'success', message: $newStatus === CustomerStatus::Active ? 'Cliente reactivado' : 'Cliente desactivado');
    }

    #[Computed]
    public function statuses()
    {
        return DelinquencyStatus::query()->ordered()->get(['id', 'name', 'color']);
    }

    protected function query(): Builder
    {
        return Customer::query()
            ->search($this->search)
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->delinquency === 'none', fn (Builder $q) => $q->whereNull('delinquency_status_id'))
            ->when(is_numeric($this->delinquency), fn (Builder $q) => $q->where('delinquency_status_id', (int) $this->delinquency))
            ->when(is_numeric($this->balanceMin), fn (Builder $q) => $q->where('outstanding_balance', '>=', (float) $this->balanceMin))
            ->when(is_numeric($this->balanceMax), fn (Builder $q) => $q->where('outstanding_balance', '<=', (float) $this->balanceMax))
            ->when($this->consent === 'promotions', fn (Builder $q) => $q->where('accepts_promotions', true)->whereNull('whatsapp_opted_out_at'))
            ->when($this->consent === 'opted_out', fn (Builder $q) => $q->whereNotNull('whatsapp_opted_out_at'));
    }

    public function render()
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'full_name';

        return view('livewire.customers.index', [
            'customers' => $this->query()
                ->with('delinquencyStatus:id,name,color')
                ->withCount(['articles' => fn ($q) => $q->where('balance', '>', 0)])
                ->orderBy($sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc')
                ->orderBy('id')
                ->paginate($this->perPage),
            'totals' => $this->query()->selectRaw('COUNT(*) as total, COALESCE(SUM(outstanding_balance),0) as balance')->toBase()->first(),
            'hasFilters' => $this->search !== '' || $this->delinquency !== '' || $this->balanceMin !== '' || $this->balanceMax !== '' || $this->consent !== '' || $this->status !== 'active',
        ]);
    }
}
