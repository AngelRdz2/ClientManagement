<?php

namespace App\Exports;

use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Services\Import\ImportColumns;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reporte de errores de una importación: fila, errores y los datos originales para corregir y volver a cargar.
 */
class ImportErrorsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private ImportBatch $batch) {}

    public function query(): Builder
    {
        return ImportError::query()->where('import_batch_id', $this->batch->id)->orderBy('row_number');
    }

    public function headings(): array
    {
        return array_merge(['fila', 'errores'], ImportColumns::headings());
    }

    public function map($error): array
    {
        $data = $error->row_data ?? [];

        return array_merge(
            [$error->row_number, implode(' | ', $error->errors ?? [])],
            array_map(fn ($column) => $data[$column] ?? '', ImportColumns::headings()),
        );
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]], 'B' => ['font' => ['color' => ['argb' => 'FFBE123C']]]];
    }
}
