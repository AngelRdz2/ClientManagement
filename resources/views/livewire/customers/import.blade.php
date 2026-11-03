<div @if ($polling) wire:poll.3s @endif>
    <x-page-header title="Importar clientes desde Excel" description="Carga masiva de clientes nuevos y actualización de existentes, incluyendo artículos, contratos, saldos y estado de mora.">
        <x-slot:actions>
            <x-button variant="secondary" icon="download" :navigate="false" :href="route('customers.import.template')">Descargar plantilla</x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Pasos --}}
    @php
        $step = ! $batch ? 1 : (in_array($batch->status, [\App\Enums\ImportStatus::Uploaded, \App\Enums\ImportStatus::Validated]) ? 2 : 3);
    @endphp
    <ol class="mb-6 grid grid-cols-3 gap-2 text-xs font-medium sm:text-sm">
        @foreach ([1 => 'Subir archivo', 2 => 'Validar y previsualizar', 3 => 'Resultado'] as $n => $label)
            <li @class(['flex items-center gap-2 rounded-xl border px-3 py-2.5', 'border-indigo-200 bg-indigo-50 text-indigo-700' => $step === $n, 'border-emerald-200 bg-emerald-50 text-emerald-700' => $step > $n, 'border-slate-200 bg-white text-slate-400' => $step < $n])>
                <span @class(['grid size-6 shrink-0 place-items-center rounded-full text-[11px] font-bold', 'bg-indigo-600 text-white' => $step === $n, 'bg-emerald-500 text-white' => $step > $n, 'bg-slate-200 text-slate-500' => $step < $n])>
                    @if ($step > $n) <x-icon name="check" class="size-3.5" /> @else {{ $n }} @endif
                </span>
                {{ $label }}
            </li>
        @endforeach
    </ol>

    @if ($step === 1)
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <label x-data="{ dragging: false }" x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop="dragging = false"
                       :class="dragging ? 'border-indigo-500 bg-indigo-50/60' : 'border-slate-300 bg-white hover:border-indigo-400 hover:bg-slate-50'"
                       class="relative flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-16 text-center transition">
                    <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="absolute inset-0 cursor-pointer opacity-0">
                    <span class="grid size-16 place-items-center rounded-2xl bg-indigo-50 text-indigo-600">
                        <x-icon name="upload" class="size-8" wire:loading.remove wire:target="file" />
                        <x-icon name="loader-circle" class="size-8 animate-spin" wire:loading wire:target="file" />
                    </span>
                    <p class="mt-4 text-base font-semibold text-slate-900" wire:loading.remove wire:target="file">Arrastra tu archivo aquí o haz clic para seleccionarlo</p>
                    <p class="mt-4 text-base font-semibold text-indigo-700" wire:loading wire:target="file">Leyendo y validando el archivo…</p>
                    <p class="mt-1 text-sm text-slate-500">Formatos XLSX o CSV · Máximo 20 MB</p>
                </label>
                @error('file')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>

            <x-card title="Estructura del archivo" icon="file-spreadsheet" :padding="false">
                <div class="max-h-[420px] overflow-y-auto scrollbar-thin">
                    <ul class="divide-y divide-slate-100 text-xs">
                        @foreach ($columns as $column => [$description, $required])
                            <li class="px-5 py-2.5">
                                <p class="flex items-center gap-2"><code class="font-semibold text-slate-800">{{ $column }}</code> @if ($required)<x-badge color="indigo" size="xs">Obligatoria</x-badge>@endif</p>
                                <p class="mt-0.5 text-slate-500">{{ $description }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="border-t border-slate-100 bg-slate-50/70 px-5 py-3 text-xs text-slate-600">Una fila por artículo. Repite los datos del cliente en cada fila de sus artículos.</div>
            </x-card>
        </div>
    @endif

    @if ($step === 2 && $batch)
        @if ($batch->status === \App\Enums\ImportStatus::Failed || ! empty($analysis['missing']))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
                <p class="font-semibold">No se pudo procesar el archivo</p>
                <p class="mt-1">{{ $batch->failure_message }}</p>
                <x-button variant="secondary" size="sm" class="mt-4" icon="upload" wire:click="startOver">Subir otro archivo</x-button>
            </div>
        @else
            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-6">
                <x-stat-card label="Filas en el archivo" :value="number_format($batch->total_rows)" icon="file-spreadsheet" color="slate" />
                <x-stat-card label="Se importarán" :value="number_format($batch->valid_rows)" icon="circle-check" color="green" />
                <x-stat-card label="Con errores" :value="number_format($batch->error_rows)" icon="circle-x" color="red" />
                <x-stat-card label="Duplicados" :value="number_format($batch->duplicate_rows)" icon="copy" color="amber" hint="Repetidos en el archivo" />
                <x-stat-card label="Clientes nuevos" :value="number_format($analysis['new_customers'] ?? 0)" icon="user-plus" color="indigo" />
                <x-stat-card label="Clientes a actualizar" :value="number_format($analysis['existing_customers'] ?? 0)" icon="refresh-cw" color="blue" hint="Coinciden por código y DUI" />
            </div>

            <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-card sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3 text-sm">
                    <x-icon name="file-spreadsheet" class="size-5 text-emerald-600" />
                    <span class="font-medium text-slate-800">{{ $batch->original_filename }}</span>
                    @if ($batch->valid_rows > config('crm.import_queue_threshold'))
                        <x-badge color="indigo">Se procesará en cola</x-badge>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-button variant="ghost" size="sm" icon="x" wire:click="startOver">Cancelar</x-button>
                    @if ($batch->error_rows > 0)
                        <x-button variant="secondary" size="sm" icon="download" :navigate="false" :href="route('customers.import.errors', $batch)">Reporte de errores</x-button>
                    @endif
                    <x-button size="sm" icon="upload" :disabled="$batch->valid_rows === 0"
                              x-on:click="$confirm({ title: '¿Importar {{ number_format($batch->valid_rows) }} filas válidas?', text: '{{ $batch->error_rows > 0 ? 'Las '.$batch->error_rows.' filas con errores no se importarán.' : 'Todas las filas son válidas.' }}', confirmText: 'Importar' }, () => $wire.import())">
                        Importar {{ number_format($batch->valid_rows) }} registros
                    </x-button>
                </div>
            </div>

            <x-card title="Previsualización" :description="'Primeras '.count($analysis['preview'] ?? []).' filas'" icon="eye" :padding="false">
                <div class="overflow-x-auto scrollbar-thin">
                    <table class="table-base">
                        <thead><tr><th>Fila</th><th>Resultado</th><th>Código</th><th>Nombre</th><th>DUI</th><th>Teléfono</th><th>Estado mora</th><th>Artículo</th><th>Contrato</th><th>Saldo</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($analysis['preview'] ?? [] as $row)
                                <tr @class(['bg-rose-50/50' => $row['errors']])>
                                    <td class="font-mono text-xs">{{ $row['row'] }}</td>
                                    <td>
                                        @if ($row['errors'])
                                            <x-badge color="red">{{ $row['duplicate'] ? 'Duplicado' : 'Error' }}</x-badge>
                                            <ul class="mt-1 max-w-xs list-disc pl-4 text-[11px] text-rose-700">@foreach ($row['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>
                                        @elseif ($row['action'] === 'update')
                                            <x-badge color="blue">Actualizar</x-badge>
                                        @else
                                            <x-badge color="green">Nuevo</x-badge>
                                        @endif
                                    </td>
                                    <td class="font-mono text-xs">{{ $row['data']['codigo_cliente'] }}</td>
                                    <td class="max-w-[180px] truncate">{{ $row['data']['nombre_completo'] }}</td>
                                    <td class="font-mono text-xs">{{ $row['data']['dui'] }}</td>
                                    <td class="text-xs">{{ $row['data']['telefono_principal'] }}</td>
                                    <td class="text-xs">{{ $row['data']['estado_mora'] ?: ($row['data']['dias_mora'] !== null && $row['data']['dias_mora'] !== '' ? $row['data']['dias_mora'].' días' : '—') }}</td>
                                    <td class="max-w-[160px] truncate text-xs">{{ $row['data']['descripcion_articulo'] ?: '—' }}</td>
                                    <td class="font-mono text-xs">{{ $row['data']['numero_contrato'] ?: '—' }}</td>
                                    <td class="text-xs">{{ $row['data']['saldo_pendiente'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    @endif

    @if ($step === 3 && $batch)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card">
            @if ($polling)
                @php $pct = $batch->valid_rows ? min(100, round($batch->processed_rows / $batch->valid_rows * 100)) : 0; @endphp
                <div class="flex items-center gap-3">
                    <x-icon name="loader-circle" class="size-6 animate-spin text-indigo-600" />
                    <div>
                        <p class="font-semibold text-slate-900">{{ $batch->status === \App\Enums\ImportStatus::Queued ? 'En cola, esperando al procesador…' : 'Importando registros…' }}</p>
                        <p class="text-sm text-slate-500">{{ number_format($batch->processed_rows) }} de {{ number_format($batch->valid_rows) }} filas procesadas. Puedes salir de esta página; te notificaremos al terminar.</p>
                    </div>
                </div>
                <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600 transition-all" style="width: {{ $pct }}%"></div></div>
                @if ($batch->status === \App\Enums\ImportStatus::Queued)
                    <p class="mt-3 text-xs text-amber-700">Requiere el procesador de colas activo: <code>php artisan queue:work</code></p>
                @endif
            @elseif ($batch->status === \App\Enums\ImportStatus::Completed)
                <div class="flex items-center gap-3">
                    <span class="grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-600"><x-icon name="circle-check" class="size-7" /></span>
                    <div><p class="text-lg font-semibold text-slate-900">Importación completada</p><p class="text-sm text-slate-500">{{ $batch->original_filename }} · {{ $batch->finished_at?->format('d/m/Y h:i a') }}</p></div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
                    @foreach ([['Clientes creados', $batch->customers_created, 'text-emerald-600'], ['Clientes actualizados', $batch->customers_updated, 'text-sky-600'], ['Artículos procesados', $batch->articles_upserted, 'text-indigo-600'], ['Cambios de estado', $batch->statuses_changed, 'text-violet-600'], ['Filas con errores', $batch->error_rows, 'text-rose-600']] as [$label, $value, $tone])
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold {{ $tone }}">{{ number_format($value) }}</p></div>
                    @endforeach
                </div>
            @else
                <div class="flex items-start gap-3 text-rose-700">
                    <x-icon name="circle-x" class="size-6 shrink-0" />
                    <div><p class="font-semibold">La importación falló</p><p class="text-sm">{{ $batch->failure_message }}</p></div>
                </div>
            @endif
            <div class="mt-6 flex flex-wrap gap-2">
                <x-button variant="secondary" icon="upload" wire:click="startOver">Nueva importación</x-button>
                @if ($batch->error_rows > 0)
                    <x-button variant="secondary" icon="download" :navigate="false" :href="route('customers.import.errors', $batch)">Descargar reporte de errores</x-button>
                @endif
                <x-button icon="users" :href="route('customers.index')">Ver clientes</x-button>
            </div>
        </div>
    @endif

    @if ($previewErrors->isNotEmpty() && $step === 2)
        <x-card title="Errores encontrados" :description="'Mostrando '.$previewErrors->count().' de '.$batch->error_rows" icon="circle-alert" class="mt-6" :padding="false">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($previewErrors as $error)
                    <li class="flex gap-3 px-5 py-2.5"><span class="font-mono text-xs text-slate-500">Fila {{ $error->row_number }}</span><span class="text-rose-700">{{ implode(' · ', $error->errors) }}</span></li>
                @endforeach
            </ul>
        </x-card>
    @endif

    {{-- Historial de importaciones --}}
    <x-card title="Importaciones recientes" icon="history" class="mt-6" :padding="false">
        <div class="overflow-x-auto scrollbar-thin">
            <table class="table-base">
                <thead><tr><th>Fecha</th><th>Archivo</th><th>Usuario</th><th>Estado</th><th>Filas</th><th>Válidas</th><th>Errores</th><th>Nuevos / Act.</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recent as $item)
                        <tr wire:key="ib-{{ $item->id }}">
                            <td class="text-xs whitespace-nowrap">{{ $item->created_at->format('d/m/Y h:i a') }}</td>
                            <td class="max-w-[220px] truncate text-xs">{{ $item->original_filename }}</td>
                            <td class="text-xs">{{ $item->user?->name }}</td>
                            <td><x-badge :color="$item->status->color()">{{ $item->status->label() }}</x-badge></td>
                            <td class="tabular-nums">{{ $item->total_rows }}</td>
                            <td class="tabular-nums">{{ $item->valid_rows }}</td>
                            <td class="tabular-nums">{{ $item->error_rows }}</td>
                            <td class="text-xs tabular-nums">{{ $item->customers_created }} / {{ $item->customers_updated }}</td>
                            <td class="text-right">@if ($item->error_rows > 0)<a href="{{ route('customers.import.errors', $item) }}" class="text-xs font-semibold text-indigo-600 hover:underline">Errores</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state icon="file-spreadsheet" title="Sin importaciones" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
