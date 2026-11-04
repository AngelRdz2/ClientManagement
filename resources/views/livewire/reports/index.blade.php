<div>
    <x-page-header title="Reportes" description="Indicadores de cartera, cobranza, mensajería y atención. Filtra por fechas y exporta a Excel." />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[280px_1fr]">
        <nav class="space-y-1 rounded-2xl border border-slate-200/80 bg-surface p-2 shadow-card xl:self-start">
            @foreach ($reports as $key => [$label, $icon])
                <button wire:click="$set('report', '{{ $key }}')" @class([
                    'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition',
                    'bg-indigo-50 text-indigo-700' => $report === $key,
                    'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $report !== $key,
                ])>
                    <x-icon :name="$icon" class="size-4 shrink-0" /> {{ $label }}
                </button>
            @endforeach
        </nav>

        <div class="min-w-0 space-y-4">
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-surface p-4 shadow-card lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ $data['title'] }}</h2>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm">
                        @foreach ($data['summary'] as $label => $value)
                            <span class="text-slate-500">{{ $label }}: <strong class="text-slate-900">{{ $value }}</strong></span>
                        @endforeach
                    </div>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    @if ($dated)
                        <div class="flex gap-1">
                            @foreach (['today' => 'Hoy', 'week' => 'Semana', 'month' => 'Mes', 'last_month' => 'Mes anterior', 'year' => 'Año'] as $k => $v)
                                <button wire:click="setPeriod('{{ $k }}')" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-indigo-100 hover:text-indigo-700">{{ $v }}</button>
                            @endforeach
                        </div>
                        <x-field label="Desde" error="from"><x-input type="date" wire:model.live="from" /></x-field>
                        <x-field label="Hasta" error="to"><x-input type="date" wire:model.live="to" /></x-field>
                    @endif
                    <x-button variant="success" icon="file-down" :navigate="false" :href="route('reports.export', ['report' => $report, 'from' => $from, 'to' => $to])">Exportar Excel</x-button>
                </div>
            </div>

            <x-table-wrapper wire:loading.class="opacity-60">
                <table class="table-base">
                    <thead><tr>@foreach ($data['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr>
                                @foreach (array_values($row) as $i => $cell)
                                    <td @class(['whitespace-nowrap' => mb_strlen((string) $cell) < 40, 'max-w-xs text-xs' => mb_strlen((string) $cell) >= 40, 'tabular-nums font-medium' => is_numeric($cell)])>
                                        {{ in_array($i, $data['money_columns'] ?? []) ? money($cell) : ($cell ?? '—') }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($data['headings']) }}"><x-empty-state icon="chart-column" title="Sin datos para el período" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
                <x-slot:footer>
                    <p class="text-xs text-slate-500">Mostrando {{ $rows->count() }} de {{ number_format($data['total']) }} registros. La exportación a Excel incluye todos los registros.</p>
                </x-slot:footer>
            </x-table-wrapper>
        </div>
    </div>
</div>
