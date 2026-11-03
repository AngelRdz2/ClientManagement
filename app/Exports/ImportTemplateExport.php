<?php

namespace App\Exports;

use App\Models\DelinquencyStatus;
use App\Services\Import\ImportColumns;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Plantilla de importación: hoja de datos con ejemplos, hoja de instrucciones y hoja de estados de mora válidos.
 */
class ImportTemplateExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new class implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function title(): string
                {
                    return 'Clientes';
                }

                public function headings(): array
                {
                    return ImportColumns::headings();
                }

                public function array(): array
                {
                    $example = array_map(fn ($c) => $c[2], ImportColumns::all());
                    $second = $example;
                    $second['codigo_articulo'] = 'REF-LG14';
                    $second['descripcion_articulo'] = 'Refrigeradora LG 14 pies';
                    $second['numero_contrato'] = 'CT-2025-000124';
                    $second['precio_original'] = '720.00';
                    $second['prima'] = '0';
                    $second['valor_cuota'] = '60.00';
                    $second['cuotas_pagadas'] = '9';
                    $second['saldo_pendiente'] = '180.00';

                    return [array_values($example), array_values($second)];
                }

                public function styles(Worksheet $sheet): array
                {
                    $required = ImportColumns::required();
                    foreach (ImportColumns::headings() as $i => $heading) {
                        $column = Coordinate::stringFromColumnIndex($i + 1);
                        $sheet->getStyle("{$column}1")->getFill()->setFillType('solid')->getStartColor()->setARGB(in_array($heading, $required, true) ? 'FF4F46E5' : 'FF1E293B');
                    }
                    $sheet->freezePane('A2');

                    return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']]]];
                }
            },
            new class implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function title(): string
                {
                    return 'Instrucciones';
                }

                public function headings(): array
                {
                    return ['Columna', 'Obligatoria', 'Descripción', 'Ejemplo'];
                }

                public function array(): array
                {
                    $rows = [];
                    foreach (ImportColumns::all() as $column => [$description, $required, $example]) {
                        $rows[] = [$column, $required ? 'Sí' : 'No', $description, $example];
                    }
                    $rows[] = [];
                    $rows[] = ['Notas', '', 'Una fila por artículo/contrato. Si un cliente tiene varios artículos, repita sus datos (mismo código y DUI) en cada fila.', ''];
                    $rows[] = ['', '', 'Clientes existentes (mismo código y DUI) se actualizan; los nuevos se crean.', ''];
                    $rows[] = ['', '', 'Un artículo se identifica por numero_contrato + codigo_articulo: si ya existe, se actualiza.', ''];
                    $rows[] = ['', '', 'Las filas con errores no se importan; descargue el reporte de errores para corregirlas.', ''];

                    return $rows;
                }

                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
            new class implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
            {
                public function title(): string
                {
                    return 'Estados de mora';
                }

                public function headings(): array
                {
                    return ['Código (estado_mora)', 'Nombre', 'Días desde', 'Descripción'];
                }

                public function array(): array
                {
                    return DelinquencyStatus::active()->ordered()->get()
                        ->map(fn ($s) => [$s->code, $s->name, $s->min_days_overdue, $s->description])
                        ->all();
                }
            },
        ];
    }
}
