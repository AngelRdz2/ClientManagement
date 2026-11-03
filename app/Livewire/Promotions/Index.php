<?php

namespace App\Livewire\Promotions;

use App\Livewire\Campaigns\Index as CampaignsIndex;
use Livewire\Attributes\Locked;

/** Módulo de campañas promocionales (separado de cobranza). */
class Index extends CampaignsIndex
{
    #[Locked]
    public string $mode = 'promotion';
}
