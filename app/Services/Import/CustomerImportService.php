<?php

namespace App\Services\Import;

use App\Enums\ArticleStatus;
use App\Enums\CustomerStatus;
use App\Enums\ImportStatus;
use App\Enums\StatusChangeSource;
use App\Imports\CustomerSheetReader;
use App\Models\Customer;
use App\Models\CustomerArticle;
use App\Models\DelinquencyStatus;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Rules\ValidPhone;
use App\Services\ActivityLogger;
use App\Services\DelinquencyService;
use App\Services\NotificationService;
use App\Support\Dui;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Importación masiva de clientes, artículos, saldos y estados de mora desde XLSX/CSV.
 *
 * Flujo: leer → analizar (validación por fila + duplicados) → procesar filas válidas (inmediato o en cola).
 */
class CustomerImportService
{
    private const DISK = 'local';

    /** @var Collection<int, DelinquencyStatus>|null */
    private ?Collection $statuses = null;

    public function __construct(private DelinquencyService $delinquency) {}

    /* ============================== Lectura ============================== */

    /** @return list<array{row: int, data: array<string, mixed>}> */
    public function readRows(ImportBatch $batch): array
    {
        $path = Storage::disk(self::DISK)->path($batch->stored_path);

        if (Str::endsWith(Str::lower($batch->stored_path), ['.csv', '.txt'])) {
            config(['excel.imports.csv.delimiter' => $this->detectCsvDelimiter($path)]);
            config(['excel.imports.csv.input_encoding' => 'UTF-8']);
        }

        $sheets = Excel::toArray(new CustomerSheetReader, $batch->stored_path, self::DISK);
        $rows = [];

        foreach ($sheets[0] ?? [] as $index => $raw) {
            $data = [];
            foreach (ImportColumns::headings() as $column) {
                $value = $raw[$column] ?? null;
                // Excel elimina ceros a la izquierda en celdas numéricas: el DUI siempre tiene 9 dígitos.
                if ($column === 'dui' && (is_int($value) || is_float($value))) {
                    $value = str_pad((string) (int) $value, 9, '0', STR_PAD_LEFT);
                }
                $data[$column] = $value === null ? null : trim((string) $value);
            }
            // Ignorar filas completamente vacías
            if (collect($data)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }
            $rows[] = ['row' => $index + 2, 'data' => $data];
        }

        return $rows;
    }

    /** @return list<string> encabezados faltantes */
    public function missingHeadings(ImportBatch $batch): array
    {
        $sheets = Excel::toArray(new class implements ToArray, WithLimit
        {
            public function array(array $array): void {}

            public function limit(): int
            {
                return 1;
            }
        }, $batch->stored_path, self::DISK);

        $headings = collect($sheets[0][0] ?? [])->map(fn ($h) => Str::slug((string) $h, '_'))->all();

        return array_values(array_diff(ImportColumns::required(), $headings));
    }

    private function detectCsvDelimiter(string $path): string
    {
        $line = (string) fgets(fopen($path, 'r'));

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    /* ============================== Análisis ============================== */

    /**
     * Valida todas las filas, registra los errores en import_errors y devuelve el resumen y la previsualización.
     *
     * @return array{total:int, valid:int, errors:int, duplicates:int, new_customers:int, existing_customers:int, articles:int, preview: list<array>}
     */
    public function analyze(ImportBatch $batch): array
    {
        $missing = $this->missingHeadings($batch);
        if ($missing !== []) {
            $batch->update(['status' => ImportStatus::Failed, 'failure_message' => 'Faltan columnas obligatorias: '.implode(', ', $missing)]);

            return ['total' => 0, 'valid' => 0, 'errors' => 0, 'duplicates' => 0, 'new_customers' => 0, 'existing_customers' => 0, 'articles' => 0, 'preview' => [], 'missing' => $missing];
        }

        $rows = $this->readRows($batch);
        $results = $this->validateRows($rows);

        DB::transaction(function () use ($batch, $results) {
            $batch->importErrors()->delete();
            foreach (array_chunk(array_values(array_filter($results['rows'], fn ($r) => $r['errors'] !== [])), 500) as $chunk) {
                ImportError::insert(array_map(fn ($r) => [
                    'import_batch_id' => $batch->id,
                    'row_number' => $r['row'],
                    'errors' => json_encode($r['errors'], JSON_UNESCAPED_UNICODE),
                    'row_data' => json_encode($r['data'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ], $chunk));
            }

            $batch->update([
                'status' => ImportStatus::Validated,
                'total_rows' => $results['total'],
                'valid_rows' => $results['valid'],
                'error_rows' => $results['errors'],
                'duplicate_rows' => $results['duplicates'],
                'failure_message' => null,
            ]);
        });

        $results['preview'] = array_slice($results['rows'], 0, (int) config('crm.import_max_preview_rows', 50));
        unset($results['rows']);

        return $results + ['missing' => []];
    }

    /**
     * @param  list<array{row:int, data:array}>  $rows
     * @return array{total:int, valid:int, errors:int, duplicates:int, new_customers:int, existing_customers:int, articles:int, rows: list<array>}
     */
    public function validateRows(array $rows): array
    {
        $codes = collect($rows)->pluck('data.codigo_cliente')->filter()->map(fn ($v) => (string) $v)->unique();
        $duis = collect($rows)->map(fn ($r) => Dui::normalize((string) $r['data']['dui']))->filter()->unique();

        $existingByCode = Customer::whereIn('code', $codes)->get(['id', 'code', 'dui'])->keyBy('code');
        $existingByDui = Customer::whereIn('dui', $duis)->get(['id', 'code', 'dui'])->keyBy('dui');

        $seenCodeDui = [];   // código => [dui, fila]
        $seenDuiCode = [];   // dui => [código, fila]
        $seenArticles = [];  // código|contrato|artículo => fila
        $result = ['total' => count($rows), 'valid' => 0, 'errors' => 0, 'duplicates' => 0, 'articles' => 0, 'rows' => []];
        $newCustomers = [];
        $existingCustomers = [];

        foreach ($rows as ['row' => $rowNumber, 'data' => $data]) {
            $errors = $this->validateRow($data);
            $isDuplicate = false;
            $code = (string) $data['codigo_cliente'];
            $dui = Dui::normalize((string) $data['dui']);
            $action = null;

            if ($errors === [] && $dui) {
                // Consistencia dentro del archivo
                if (isset($seenCodeDui[$code]) && $seenCodeDui[$code][0] !== $dui) {
                    $errors[] = "El código {$code} aparece con otro DUI en la fila {$seenCodeDui[$code][1]}.";
                }
                if (isset($seenDuiCode[$dui]) && $seenDuiCode[$dui][0] !== $code) {
                    $errors[] = "El DUI {$dui} aparece con otro código de cliente en la fila {$seenDuiCode[$dui][1]}.";
                }
                $seenCodeDui[$code] ??= [$dui, $rowNumber];
                $seenDuiCode[$dui] ??= [$code, $rowNumber];

                if ($this->hasArticle($data)) {
                    $articleKey = Str::lower($code.'|'.$data['numero_contrato'].'|'.$data['codigo_articulo']);
                    if (isset($seenArticles[$articleKey])) {
                        $errors[] = "Registro duplicado: el contrato {$data['numero_contrato']} / artículo {$data['codigo_articulo']} ya aparece en la fila {$seenArticles[$articleKey]}.";
                        $isDuplicate = true;
                    }
                    $seenArticles[$articleKey] ??= $rowNumber;
                }

                // Coincidencias con la base de datos (duplicados por DUI o código)
                $byCode = $existingByCode->get($code);
                $byDui = $existingByDui->get($dui);
                if ($byCode && $byCode->dui !== $dui) {
                    $errors[] = "El código {$code} ya existe con otro DUI ({$byCode->dui}).";
                } elseif ($byDui && $byDui->code !== $code) {
                    $errors[] = "El DUI {$dui} ya está registrado para el cliente {$byDui->code}.";
                } elseif ($byCode) {
                    $action = 'update';
                    $existingCustomers[$code] = true;
                } else {
                    $action = 'create';
                    $newCustomers[$code] = true;
                }
            }

            if ($errors === []) {
                $result['valid']++;
                $result['articles'] += $this->hasArticle($data) ? 1 : 0;
            } else {
                $result['errors']++;
                $result['duplicates'] += $isDuplicate ? 1 : 0;
                $action = 'error';
            }

            $result['rows'][] = ['row' => $rowNumber, 'data' => $data, 'errors' => $errors, 'action' => $action, 'duplicate' => $isDuplicate];
        }

        $result['new_customers'] = count($newCustomers);
        $result['existing_customers'] = count($existingCustomers);

        return $result;
    }

    /** @return list<string> */
    public function validateRow(array $data): array
    {
        $rules = [
            'codigo_cliente' => ['required', 'string', 'max:30'],
            'nombre_completo' => ['required', 'string', 'min:3', 'max:200'],
            'dui' => ['required'],
            'telefono_principal' => ['required', new ValidPhone],
            'telefono_secundario' => ['nullable', new ValidPhone],
            'correo' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'max:500'],
            'estado_cliente' => ['nullable', 'in:activo,inactivo,ACTIVO,INACTIVO,Activo,Inactivo'],
            'dias_mora' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'observaciones' => ['nullable', 'max:2000'],
        ];

        $messages = [];
        $validator = Validator::make($data, $rules, [], array_combine(array_keys($rules), array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($rules))));
        foreach ($validator->errors()->all() as $message) {
            $messages[] = $message;
        }

        if (filled($data['dui']) && ! Dui::isValid((string) $data['dui'])) {
            $messages[] = 'DUI inválido ('.$data['dui'].'): debe tener 9 dígitos y un dígito verificador correcto.';
        }
        if (filled($data['fecha_registro']) && $this->parseDate($data['fecha_registro']) === null) {
            $messages[] = 'fecha_registro no tiene un formato válido (use dd/mm/aaaa).';
        }
        if (filled($data['estado_mora']) && $this->findStatus((string) $data['estado_mora']) === null) {
            $messages[] = "estado_mora “{$data['estado_mora']}” no existe o está inactivo.";
        }
        if (filled($data['acepta_promociones']) && $this->parseBool($data['acepta_promociones']) === null) {
            $messages[] = 'acepta_promociones debe ser si o no.';
        }

        if ($this->hasArticle($data)) {
            foreach (['codigo_articulo', 'descripcion_articulo', 'numero_contrato'] as $field) {
                if (blank($data[$field])) {
                    $messages[] = "{$field} es obligatorio cuando la fila incluye un artículo.";
                }
            }
            foreach (['precio_original', 'prima', 'valor_cuota', 'saldo_pendiente'] as $field) {
                if (filled($data[$field]) && ($this->parseNumber($data[$field]) === null || $this->parseNumber($data[$field]) < 0)) {
                    $messages[] = "{$field} debe ser un número mayor o igual a cero.";
                }
            }
            foreach (['cantidad_cuotas', 'cuotas_pagadas'] as $field) {
                if (filled($data[$field]) && (! is_numeric($data[$field]) || (int) $data[$field] < 0 || (float) $data[$field] != (int) $data[$field])) {
                    $messages[] = "{$field} debe ser un número entero mayor o igual a cero.";
                }
            }
            if (is_numeric($data['cantidad_cuotas']) && is_numeric($data['cuotas_pagadas']) && (int) $data['cuotas_pagadas'] > (int) $data['cantidad_cuotas']) {
                $messages[] = 'cuotas_pagadas no puede ser mayor que cantidad_cuotas.';
            }
            foreach (['fecha_adquisicion', 'proxima_fecha_pago'] as $field) {
                if (filled($data[$field]) && $this->parseDate($data[$field]) === null) {
                    $messages[] = "{$field} no tiene un formato válido (use dd/mm/aaaa).";
                }
            }
            if (filled($data['estado_articulo']) && $this->parseArticleStatus((string) $data['estado_articulo']) === null) {
                $messages[] = 'estado_articulo debe ser vigente, cancelado, anulado o en_cobranza.';
            }
        }

        return $messages;
    }

    /* ============================ Procesamiento ============================ */

    public function process(ImportBatch $batch): void
    {
        $batch->update(['status' => ImportStatus::Processing, 'started_at' => now(), 'processed_rows' => 0]);
        $user = $batch->user;
        $this->delinquency->flushCache();

        try {
            $validated = $this->validateRows($this->readRows($batch));
            $validRows = collect($validated['rows'])->filter(fn ($r) => $r['errors'] === []);
            $counters = ['customers_created' => 0, 'customers_updated' => 0, 'articles_upserted' => 0, 'statuses_changed' => 0, 'processed_rows' => 0];

            foreach ($validRows->groupBy(fn ($r) => (string) $r['data']['codigo_cliente']) as $customerRows) {
                DB::transaction(function () use ($customerRows, $batch, $user, &$counters) {
                    $this->importCustomer($customerRows, $batch, $user, $counters);
                });

                $counters['processed_rows'] += $customerRows->count();
                $batch->update($counters);
            }

            $batch->update($counters + ['status' => ImportStatus::Completed, 'finished_at' => now()]);

            ActivityLogger::log('import.completed', "Importación “{$batch->original_filename}”: {$counters['customers_created']} clientes nuevos, {$counters['customers_updated']} actualizados, {$counters['articles_upserted']} artículos", $batch, $counters, $user?->id);

            app(NotificationService::class)->toUser($user, new SystemNotification(
                'import_completed',
                'Importación completada',
                "{$batch->original_filename}: {$counters['customers_created']} nuevos, {$counters['customers_updated']} actualizados".($batch->error_rows ? ", {$batch->error_rows} filas con errores" : '').'.',
                'file-spreadsheet', 'green', route('customers.import'),
            ));
        } catch (Throwable $e) {
            report($e);
            $batch->update(['status' => ImportStatus::Failed, 'failure_message' => Str::limit($e->getMessage(), 900), 'finished_at' => now()]);
            ActivityLogger::log('import.failed', "Error en la importación “{$batch->original_filename}”", $batch, ['error' => $e->getMessage()], $user?->id);

            app(NotificationService::class)->toPermission('customers.import', new SystemNotification(
                'import_failed', 'Error en una importación', "La importación de {$batch->original_filename} falló. Revisa el detalle.", 'triangle-alert', 'red', route('customers.import'),
            ));

            throw $e;
        }
    }

    /** @param Collection<int, array> $rows filas válidas del mismo cliente */
    private function importCustomer(Collection $rows, ImportBatch $batch, ?User $user, array &$counters): void
    {
        $first = $rows->first()['data'];
        $code = (string) $first['codigo_cliente'];
        $customer = Customer::where('code', $code)->first();
        $isNew = $customer === null;
        $customer ??= new Customer(['code' => $code, 'created_by' => $user?->id]);

        $promotions = filled($first['acepta_promociones']) ? $this->parseBool($first['acepta_promociones']) : null;
        $attributes = array_filter([
            'full_name' => $first['nombre_completo'],
            'dui' => Dui::normalize((string) $first['dui']),
            'phone_primary' => (string) $first['telefono_principal'],
            'phone_secondary' => filled($first['telefono_secundario']) ? (string) $first['telefono_secundario'] : null,
            'email' => $first['correo'] ?: null,
            'address' => $first['direccion'] ?: null,
            'registered_at' => filled($first['fecha_registro']) ? $this->parseDate($first['fecha_registro'])?->toDateString() : null,
            'status' => filled($first['estado_cliente']) ? (Str::lower($first['estado_cliente']) === 'inactivo' ? CustomerStatus::Inactive : CustomerStatus::Active) : null,
            'notes' => $first['observaciones'] ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $customer->fill($attributes);
        if ($isNew) {
            $customer->registered_at ??= now()->toDateString();
            $customer->status ??= CustomerStatus::Active;
        } else {
            $customer->updated_by = $user?->id;
        }
        if ($promotions !== null && $promotions !== (bool) $customer->accepts_promotions) {
            $customer->accepts_promotions = $promotions;
            $customer->promotions_consent_at = $promotions ? now() : null;
            $customer->promotions_consent_source = $promotions ? 'importación Excel' : null;
        }
        $customer->save();
        $counters[$isNew ? 'customers_created' : 'customers_updated']++;

        foreach ($rows as ['data' => $data]) {
            if (! $this->hasArticle($data)) {
                continue;
            }
            $article = CustomerArticle::firstOrNew([
                'customer_id' => $customer->id,
                'contract_number' => (string) $data['numero_contrato'],
                'article_code' => (string) $data['codigo_articulo'],
            ]);

            $count = filled($data['cantidad_cuotas']) ? (int) $data['cantidad_cuotas'] : (int) $article->installments_count;
            $paid = filled($data['cuotas_pagadas']) ? (int) $data['cuotas_pagadas'] : (int) $article->installments_paid;
            $installment = filled($data['valor_cuota']) ? $this->parseNumber($data['valor_cuota']) : (float) $article->installment_amount;
            $balance = filled($data['saldo_pendiente']) ? $this->parseNumber($data['saldo_pendiente']) : round(max(0, $count - $paid) * $installment, 2);

            $article->fill(array_filter([
                'description' => (string) $data['descripcion_articulo'],
                'acquired_at' => filled($data['fecha_adquisicion']) ? $this->parseDate($data['fecha_adquisicion'])?->toDateString() : null,
                'original_price' => filled($data['precio_original']) ? $this->parseNumber($data['precio_original']) : null,
                'down_payment' => filled($data['prima']) ? $this->parseNumber($data['prima']) : null,
                'next_payment_date' => filled($data['proxima_fecha_pago']) ? $this->parseDate($data['proxima_fecha_pago'])?->toDateString() : null,
                'status' => filled($data['estado_articulo']) ? $this->parseArticleStatus((string) $data['estado_articulo']) : null,
            ], fn ($v) => $v !== null));
            $article->installments_count = $count;
            $article->installments_paid = $paid;
            $article->installment_amount = $installment;
            $article->balance = $balance;
            $article->status ??= ArticleStatus::Active;
            $article->save();
            $counters['articles_upserted']++;
        }

        $this->delinquency->updateArticleDays($customer);

        // Estado de mora: explícito por código/nombre, o calculado con los días indicados.
        $days = filled($first['dias_mora']) ? (int) $first['dias_mora'] : null;
        $status = filled($first['estado_mora'])
            ? $this->findStatus((string) $first['estado_mora'])
            : ($days !== null ? $this->delinquency->statusForDays($days) : ($isNew ? $this->delinquency->statusForDays(0) : null));

        if ($status !== null || $days !== null) {
            $fresh = $customer->fresh('delinquencyStatus');
            $changed = $this->delinquency->changeStatus(
                $fresh,
                $status ?? $fresh->delinquencyStatus,
                $days,
                StatusChangeSource::Import,
                "Importación de Excel: {$batch->original_filename}",
                $user,
            );
            $counters['statuses_changed'] += $changed ? 1 : 0;
        }
    }

    /* ============================== Utilidades ============================== */

    private function hasArticle(array $data): bool
    {
        return filled($data['codigo_articulo']) || filled($data['descripcion_articulo']) || filled($data['numero_contrato']);
    }

    public function findStatus(string $value): ?DelinquencyStatus
    {
        $this->statuses ??= DelinquencyStatus::active()->get();
        $needle = Str::lower(Str::ascii(trim($value)));

        return $this->statuses->first(fn (DelinquencyStatus $s) => Str::lower($s->code) === $needle || Str::lower(Str::ascii($s->name)) === $needle);
    }

    public function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'Y/m/d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, (string) $value);
                if ($date && $date->format($format) === (string) $value) {
                    return $date;
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    public function parseNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        $clean = str_replace(['$', ',', ' '], '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    public function parseBool(mixed $value): ?bool
    {
        return match (Str::lower(Str::ascii(trim((string) $value)))) {
            'si', 's', 'yes', 'y', '1', 'true', 'x' => true,
            'no', 'n', '0', 'false' => false,
            default => null,
        };
    }

    public function parseArticleStatus(string $value): ?ArticleStatus
    {
        return match (Str::lower(Str::ascii(str_replace(' ', '_', trim($value))))) {
            'vigente', 'activo', 'active' => ArticleStatus::Active,
            'cancelado', 'pagado', 'paid_off' => ArticleStatus::PaidOff,
            'anulado', 'cancelled' => ArticleStatus::Cancelled,
            'en_cobranza', 'cobranza', 'in_collection' => ArticleStatus::InCollection,
            default => null,
        };
    }
}
