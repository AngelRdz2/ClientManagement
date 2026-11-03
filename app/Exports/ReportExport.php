<?php

namespace App\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación genérica de reportes (filas generadas de forma perezosa para soportar volúmenes grandes).
 */
class ReportExport implements FromGenerator, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private array $report) {}

    public function generator(): Generator
    {
        foreach ($this->report['rows'] as $row) {
            yield $row;
        }
    }

    public function headings(): array
    {
        return $this->report['headings'];
    }

    public function title(): string
    {
        return mb_substr($this->report['title'], 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1E1B4B']]]];
    }
}
