<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Models\Campaign;
use App\Services\Messaging\BusinessHours;
use App\Services\Messaging\CampaignService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Procesa una campaña en segundo plano: genera los mensajes y los envía a la cola de WhatsApp.
 * Las campañas de cobranza solo se envían dentro de la ventana horaria configurada.
 */
class ProcessCampaignJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 0;

    public function __construct(public int $campaignId) {}

    public function uniqueId(): string
    {
        return (string) $this->campaignId;
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(2);
    }

    public function handle(CampaignService $service): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (! $campaign || $campaign->status !== CampaignStatus::Processing) {
            return;
        }

        if ($campaign->type === CampaignType::Collection && ! BusinessHours::withinCollectionWindow()) {
            $this->release(BusinessHours::secondsUntilCollectionWindow());

            return;
        }

        $service->dispatchPending($campaign);
    }
}
