<?php

namespace App\Http\Controllers;

use App\Exports\ImportErrorsExport;
use App\Exports\ImportTemplateExport;
use App\Models\ImportBatch;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportDownloadController extends Controller
{
    public function template(): BinaryFileResponse
    {
        return Excel::download(new ImportTemplateExport, 'plantilla_importacion_clientes.xlsx');
    }

    public function errors(ImportBatch $batch): BinaryFileResponse
    {
        return Excel::download(new ImportErrorsExport($batch), 'errores_importacion_'.$batch->id.'.xlsx');
    }
}
