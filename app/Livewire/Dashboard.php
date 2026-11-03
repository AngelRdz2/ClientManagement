<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Services\DashboardMetrics;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function refresh(): void
    {
        $this->dispatch('toast', type: 'success', message: 'Indicadores actualizados');
    }

    public function render(DashboardMetrics $metrics)
    {
        $distribution = $metrics->delinquencyDistribution();
        $trend = $metrics->collectionTrend();
        $messages = $metrics->messagesByDay();

        return view('livewire.dashboard', [
            'kpis' => $metrics->kpis(),
            'breakdown' => $metrics->customerBreakdown(),
            'distribution' => $distribution,
            'charts' => [
                'distribution' => [
                    'type' => 'doughnut',
                    'labels' => $distribution['labels'],
                    'datasets' => [['label' => 'Clientes', 'data' => $distribution['counts'], 'backgroundColor' => $distribution['colors'], 'borderWidth' => 2, 'borderColor' => '#fff']],
                ],
                'balanceByStatus' => [
                    'type' => 'bar',
                    'money' => true,
                    'labels' => $distribution['labels'],
                    'datasets' => [['label' => 'Saldo pendiente', 'data' => $distribution['balances'], 'backgroundColor' => $distribution['colors'], 'borderRadius' => 6, 'maxBarThickness' => 38]],
                ],
                'collection' => [
                    'type' => 'line',
                    'money' => true,
                    'labels' => $trend['labels'],
                    'datasets' => [[
                        'label' => 'Monto cobrado', 'data' => $trend['totals'], 'borderColor' => '#4f46e5', 'backgroundColor' => 'rgba(79,70,229,0.08)',
                        'fill' => true, 'tension' => 0.35, 'pointRadius' => 3, 'pointBackgroundColor' => '#4f46e5', 'borderWidth' => 2.5,
                    ]],
                ],
                'messages' => [
                    'type' => 'bar',
                    'stacked' => true,
                    'legend' => true,
                    'labels' => $messages['labels'],
                    'datasets' => [
                        ['label' => 'Enviados', 'data' => $messages['sent'], 'backgroundColor' => '#6366f1', 'borderRadius' => 4, 'maxBarThickness' => 26],
                        ['label' => 'Pendientes', 'data' => $messages['pending'], 'backgroundColor' => '#cbd5e1', 'borderRadius' => 4, 'maxBarThickness' => 26],
                        ['label' => 'Fallidos', 'data' => $messages['failed'], 'backgroundColor' => '#f43f5e', 'borderRadius' => 4, 'maxBarThickness' => 26],
                    ],
                ],
            ],
            'activity' => ActivityLog::query()->with('user:id,name')->latest('id')->limit(8)->get(),
        ]);
    }
}
