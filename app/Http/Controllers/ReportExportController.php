<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Services\ActivityLogger;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): BinaryFileResponse
    {
        $data = $request->validate([
            'report' => ['required', 'in:'.implode(',', array_keys(ReportService::REPORTS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $report = $reports->run($data['report'], $data['from'] ?? null, $data['to'] ?? null);
        ActivityLogger::log('report.exported', "Exportó el reporte “{$report['title']}”", null, $data);

        return Excel::download(new ReportExport($report), $data['report'].'_'.now()->format('Ymd_His').'.xlsx');
    }
}
