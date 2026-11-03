<?php

namespace App\Livewire\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Models\Campaign;
use App\Services\Messaging\CampaignService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    #[Locked]
    public int $campaignId;

    #[Url(except: '')]
    public string $recipientStatus = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(Campaign $campaign): void
    {
        $this->authorize('campaigns.manage');
        $this->campaignId = $campaign->id;
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    protected function campaign(): Campaign
    {
        return Campaign::findOrFail($this->campaignId);
    }

    public function launch(CampaignService $service): void
    {
        try {
            $service->launch($this->campaign());
            $this->dispatch('toast', type: 'success', message: $this->campaign()->status === CampaignStatus::Scheduled ? 'Campaña programada' : 'Campaña iniciada');
        } catch (\RuntimeException $e) {
            $this->dispatch('alert', type: 'error', title: 'No se pudo iniciar', message: $e->getMessage());
        }
    }

    public function cancel(CampaignService $service): void
    {
        $campaign = $this->campaign();
        if (in_array($campaign->status, [CampaignStatus::Completed, CampaignStatus::Cancelled], true)) {
            return;
        }
        $service->cancel($campaign);
        $this->dispatch('toast', type: 'success', message: 'Campaña cancelada');
    }

    public function retryFailed(CampaignService $service): void
    {
        $count = $service->retryFailed($this->campaign());
        $this->dispatch('toast', type: $count ? 'success' : 'info', message: $count ? "{$count} envíos reenviados a la cola" : 'No hay envíos fallidos');
    }

    public function render()
    {
        $campaign = Campaign::with(['template:id,name,body', 'creator:id,name'])->findOrFail($this->campaignId);
        $isPromotion = $campaign->type === CampaignType::Promotion;

        // Advertencia si hay trabajos en cola sin procesar desde hace más de 2 minutos (worker detenido).
        $staleQueue = in_array($campaign->status, [CampaignStatus::Processing], true)
            && DB::table('jobs')->where('available_at', '<', now()->subMinutes(2)->timestamp)->exists();

        return view('livewire.campaigns.show', [
            'campaign' => $campaign,
            'recipients' => $campaign->recipients()
                ->with(['customer:id,code,full_name,delinquency_status_id', 'customer.delinquencyStatus:id,name,color', 'message:id,status,error_message,sent_at,delivered_at,read_at'])
                ->when($this->recipientStatus !== '', fn ($q) => $q->where('status', $this->recipientStatus))
                ->when($this->search !== '', fn ($q) => $q->whereHas('customer', fn ($c) => $c->search($this->search)))
                ->orderBy('id')
                ->paginate(15),
            'prefix' => $isPromotion ? 'promotions' : 'campaigns',
            'isPromotion' => $isPromotion,
            'polling' => in_array($campaign->status, [CampaignStatus::Processing, CampaignStatus::Scheduled], true),
            'staleQueue' => $staleQueue,
        ])->title($campaign->name);
    }
}
