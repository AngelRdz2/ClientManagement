<?php

namespace App\Livewire\Campaigns;

use App\Enums\CampaignType;
use App\Models\Campaign;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de campañas de cobranza / recordatorios. La subclase Promotions\Index muestra solo promociones.
 */
class Index extends Component
{
    use WithPagination;

    #[Locked]
    public string $mode = 'collection';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('campaigns.manage');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    protected function types(): array
    {
        return $this->mode === 'promotion'
            ? [CampaignType::Promotion->value]
            : [CampaignType::Collection->value, CampaignType::Reminder->value, CampaignType::Custom->value];
    }

    public function routePrefix(): string
    {
        return $this->mode === 'promotion' ? 'promotions' : 'campaigns';
    }

    public function render()
    {
        $base = Campaign::query()->whereIn('type', $this->types());

        return view('livewire.campaigns.index', [
            'campaigns' => (clone $base)
                ->with(['template:id,name', 'creator:id,name'])
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->latest('id')
                ->paginate(12),
            'stats' => (clone $base)->selectRaw('COUNT(*) as total, COALESCE(SUM(sent_count),0) as sent, COALESCE(SUM(failed_count),0) as failed, COALESCE(SUM(total_recipients),0) as recipients')->toBase()->first(),
            'prefix' => $this->routePrefix(),
            'isPromotion' => $this->mode === 'promotion',
        ])->title($this->mode === 'promotion' ? 'Campañas promocionales' : 'Campañas de cobranza');
    }
}
