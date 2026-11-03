<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lector de la primera hoja del archivo de clientes. La fila 1 contiene los encabezados (convertidos a slug).
 */
class CustomerSheetReader implements ToArray, WithCalculatedFormulas, WithHeadingRow
{
    public function array(array $array): void
    {
        // La lectura se realiza con Excel::toArray(); este método no se utiliza.
    }
}
