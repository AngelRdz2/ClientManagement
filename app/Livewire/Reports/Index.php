<?php

namespace App\Livewire\Reports;

use App\Services\Reports\ReportService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Reportes')]
class Index extends Component
{
    #[Url]
    public string $report = 'customers_by_status';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('reports.view');
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
        if (! isset(ReportService::REPORTS[$this->report])) {
            $this->report = 'customers_by_status';
        }
    }

    public function setPeriod(string $period): void
    {
        [$this->from, $this->to] = match ($period) {
            'today' => [now()->toDateString(), now()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->toDateString()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        };
    }

    public function render(ReportService $reports)
    {
        // Fechas inválidas o invertidas se corrigen en lugar de interrumpir el reporte.
        $valid = fn ($d) => is_string($d) && strtotime($d) !== false;
        $this->from = $valid($this->from) ? $this->from : now()->startOfMonth()->toDateString();
        $this->to = $valid($this->to) ? $this->to : now()->toDateString();
        if ($this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }

        $data = $reports->run($this->report, $this->from, $this->to, limit: 100);
        $rows = collect($data['rows'])->values();

        return view('livewire.reports.index', [
            'reports' => ReportService::REPORTS,
            'data' => $data,
            'rows' => $rows,
            'dated' => ReportService::REPORTS[$this->report][2],
        ]);
    }
}
