<?php

namespace App\Livewire\Dashboard;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Livewire\Component;

/**
 * Alerta del dashboard con los clientes que solicitaron hablar con un asesor (se refresca cada 15 s).
 */
class AgentAlerts extends Component
{
    public function render()
    {
        $pending = Conversation::query()
            ->where('status', ConversationStatus::PendingAgent)
            ->with('customer:id,full_name,code')
            ->oldest('agent_requested_at')
            ->limit(5)
            ->get();

        return view('livewire.dashboard.agent-alerts', [
            'pending' => $pending,
            'total' => $pending->count() < 5 ? $pending->count() : Conversation::where('status', ConversationStatus::PendingAgent)->count(),
        ]);
    }
}
