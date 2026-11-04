<?php

namespace App\Livewire\Customers;

use App\Enums\StatusChangeSource;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DelinquencyStatus;
use App\Services\DelinquencyService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tablero tipo kanban: clientes activos agrupados en columnas por estado de mora.
 * Arrastrar una tarjeta a otra columna abre la confirmación del cambio de estado (con motivo, como en la ficha).
 */
#[Title('Tablero de clientes')]
class Board extends Component
{
    private const PER_COLUMN = 20;

    private const SORTS = [
        'urgency' => [['days_overdue', 'desc'], ['outstanding_balance', 'desc']],
        'balance' => [['outstanding_balance', 'desc'], ['full_name', 'asc']],
        'name' => [['full_name', 'asc']],
    ];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: 'urgency')]
    public string $sort = 'urgency';

    /** @var array<string, int> Tarjetas visibles por columna (clave: id del estado o 'none') */
    public array $limits = [];

    public bool $showMoveModal = false;

    public ?int $moveCustomerId = null;

    public ?int $moveFromStatusId = null;

    public ?int $moveStatusId = null;

    public int $moveDays = 0;

    public string $moveReason = '';

    public function mount(): void
    {
        $this->authorize('customers.view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'group', 'sort'], true)) {
            $this->limits = [];
        }
    }

    public function loadMore(string $column): void
    {
        $this->limits[$column] = ($this->limits[$column] ?? self::PER_COLUMN) + self::PER_COLUMN;
    }

    /** Llamado por wire:sort al soltar una tarjeta: $column es el id del estado de la columna destino. */
    public function moveCustomer(int $customerId, int $position, string $column): void
    {
        if (! auth()->user()->can('customers.manage') || ! is_numeric($column)) {
            return;
        }

        $customer = Customer::find($customerId);
        $status = DelinquencyStatus::active()->find((int) $column);
        if (! $customer || ! $status || $customer->delinquency_status_id === $status->id) {
            return;
        }

        $this->resetValidation();
        $this->moveCustomerId = $customer->id;
        $this->moveFromStatusId = $customer->delinquency_status_id;
        $this->moveStatusId = $status->id;
        $this->moveDays = match (true) {
            $status->is_solvent => 0,
            $customer->days_overdue < $status->min_days_overdue => $status->min_days_overdue,
            default => $customer->days_overdue,
        };
        $this->moveReason = '';
        $this->showMoveModal = true;
    }

    public function saveMove(DelinquencyService $service): void
    {
        $this->authorize('customers.manage');
        $this->validate([
            'moveCustomerId' => ['required', 'exists:customers,id'],
            'moveStatusId' => ['required', 'exists:delinquency_statuses,id'],
            'moveDays' => ['required', 'integer', 'min:0', 'max:5000'],
            'moveReason' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: ['moveDays' => 'días de mora', 'moveReason' => 'motivo']);

        $customer = Customer::findOrFail($this->moveCustomerId);
        $status = DelinquencyStatus::findOrFail($this->moveStatusId);
        $service->changeStatus($customer, $status, $this->moveDays, StatusChangeSource::Manual, $this->moveReason, auth()->user());

        $this->showMoveModal = false;
        $this->dispatch('toast', type: 'success', message: "{$customer->full_name} pasó a «{$status->name}»");
    }

    #[Computed]
    public function groups()
    {
        return CustomerGroup::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function moving(): ?array
    {
        if (! $this->moveCustomerId || ! $this->moveStatusId) {
            return null;
        }

        // El estado de origen se guarda al soltar la tarjeta: así el modal no cambia mientras se cierra tras aplicar
        $customer = Customer::find($this->moveCustomerId, ['id', 'full_name']);
        $from = $this->moveFromStatusId ? DelinquencyStatus::find($this->moveFromStatusId, ['id', 'name', 'color']) : null;
        $to = DelinquencyStatus::find($this->moveStatusId, ['id', 'name', 'color']);

        return $customer && $to ? ['customer' => $customer, 'from' => $from, 'to' => $to] : null;
    }

    protected function query(): Builder
    {
        return Customer::query()
            ->active()
            ->search($this->search)
            ->when(is_numeric($this->group), fn (Builder $q) => $q->whereHas('groups', fn (Builder $g) => $g->whereKey((int) $this->group)));
    }

    public function render()
    {
        $sorts = self::SORTS[$this->sort] ?? self::SORTS['urgency'];

        $totals = $this->query()
            ->selectRaw('delinquency_status_id, COUNT(*) as total, COALESCE(SUM(outstanding_balance), 0) as balance')
            ->groupBy('delinquency_status_id')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => $row->delinquency_status_id ?? 'none');

        $columns = DelinquencyStatus::query()->active()->ordered()->get(['id', 'name', 'color', 'is_solvent', 'is_critical', 'is_auto_assignable'])
            ->map(fn (DelinquencyStatus $s) => ['key' => (string) $s->id, 'status' => $s]);
        if ($totals->has('none')) {
            $columns->push(['key' => 'none', 'status' => null]);
        }

        $columns = $columns->map(function (array $column) use ($totals, $sorts) {
            $limit = $this->limits[$column['key']] ?? self::PER_COLUMN;
            $cards = $this->query()
                ->when($column['status'], fn (Builder $q, $s) => $q->where('delinquency_status_id', $s->id), fn (Builder $q) => $q->whereNull('delinquency_status_id'))
                ->withCount(['articles' => fn ($q) => $q->where('balance', '>', 0)])
                ->tap(fn (Builder $q) => collect($sorts)->each(fn ($s) => $q->orderBy($s[0], $s[1])))
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'code', 'full_name', 'phone_primary', 'days_overdue', 'outstanding_balance', 'next_payment_date', 'whatsapp_opted_out_at']);

            return $column + [
                'cards' => $cards,
                'total' => (int) ($totals[$column['key']]->total ?? 0),
                'balance' => (float) ($totals[$column['key']]->balance ?? 0),
            ];
        });

        return view('livewire.customers.board', [
            'columns' => $columns,
            'summary' => ['total' => $totals->sum('total'), 'balance' => $totals->sum('balance')],
            'canManage' => auth()->user()->can('customers.manage'),
        ]);
    }
}
