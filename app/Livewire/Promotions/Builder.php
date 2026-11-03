<?php

namespace App\Livewire\Promotions;

use App\Livewire\Campaigns\Builder as CampaignBuilder;
use Livewire\Attributes\Locked;

/** Constructor de campañas promocionales: solo plantillas de promoción y clientes con consentimiento. */
class Builder extends CampaignBuilder
{
    #[Locked]
    public string $mode = 'promotion';
}
