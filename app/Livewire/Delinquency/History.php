<?php

namespace App\Livewire\Delinquency;

use App\Enums\StatusChangeSource;
use App\Models\CustomerStatusHistory;
use App\Models\DelinquencyStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Historial de estados de mora')]
class History extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $toStatus = '';

    #[Url(except: '')]
    public string $userId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['customers.manage', 'delinquency.manage', 'reports.view']), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $history = CustomerStatusHistory::query()
            ->with(['customer:id,code,full_name', 'fromStatus:id,name,color', 'toStatus:id,name,color', 'user:id,name'])
            ->when($this->search !== '', fn (Builder $q) => $q->whereHas('customer', fn (Builder $c) => $c->search($this->search)))
            ->when($this->source !== '', fn (Builder $q) => $q->where('source', $this->source))
            ->when(is_numeric($this->toStatus), fn (Builder $q) => $q->where('to_status_id', (int) $this->toStatus))
            ->when(is_numeric($this->userId), fn (Builder $q) => $q->where('user_id', (int) $this->userId))
            ->when($this->from !== '', fn (Builder $q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn (Builder $q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id')
            ->paginate(20);

        return view('livewire.delinquency.history', [
            'history' => $history,
            'statuses' => DelinquencyStatus::query()->ordered()->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'sources' => StatusChangeSource::options(),
        ]);
    }
}
