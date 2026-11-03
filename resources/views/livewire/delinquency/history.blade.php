<div>
    <x-page-header title="Historial de estados de mora" description="Registro inmutable de cada cambio: estado anterior, nuevo, fecha, usuario, origen y motivo." :back="route('delinquency.updates')">
        <x-slot:actions>
            @if (Route::has('reports.export') && auth()->user()->can('reports.view'))
                <x-button variant="secondary" icon="download" :navigate="false" :href="route('reports.export', ['report' => 'status_history', 'from' => $from, 'to' => $to])">Exportar Excel</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-table-wrapper>
        <x-slot:toolbar>
            <div class="grid w-full grid-cols-2 gap-2 md:grid-cols-6">
                <x-input icon="search" type="search" wire:model.live.debounce.350ms="search" placeholder="Cliente…" class="col-span-2" />
                <x-select wire:model.live="source"><option value="">Origen: todos</option>@foreach ($sources as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
                <x-select wire:model.live="toStatus"><option value="">Estado nuevo: todos</option>@foreach ($statuses as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</x-select>
                <x-input type="date" wire:model.live="from" title="Desde" />
                <x-input type="date" wire:model.live="to" title="Hasta" />
            </div>
        </x-slot:toolbar>

        <table class="table-base">
            <thead><tr><th>Fecha</th><th>Cliente</th><th>Estado anterior</th><th>Estado nuevo</th><th>Días</th><th>Origen</th><th>Motivo</th><th>Usuario</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($history as $h)
                    <tr wire:key="hh-{{ $h->id }}">
                        <td class="text-xs whitespace-nowrap">{{ $h->created_at->format('d/m/Y h:i a') }}</td>
                        <td>@if ($h->customer)<a href="{{ route('customers.show', $h->customer_id) }}" wire:navigate class="font-medium text-slate-900 hover:text-indigo-600">{{ $h->customer->full_name }}</a><span class="block text-xs text-slate-500">{{ $h->customer->code }}</span>@endif</td>
                        <td><x-status-pill :status="$h->fromStatus" size="xs" /></td>
                        <td><x-status-pill :status="$h->toStatus" size="xs" /></td>
                        <td class="text-xs whitespace-nowrap">{{ $h->from_days_overdue ?? '—' }} → {{ $h->to_days_overdue ?? '—' }}</td>
                        <td><x-badge :color="$h->source->color()">{{ $h->source->label() }}</x-badge></td>
                        <td class="max-w-[260px] text-xs text-slate-600">{{ $h->reason ?? '—' }}</td>
                        <td class="text-xs">{{ $h->user?->name ?? 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state icon="history" title="Sin registros" /></td></tr>
                @endforelse
            </tbody>
        </table>
        <x-slot:footer>{{ $history->links() }}</x-slot:footer>
    </x-table-wrapper>
</div>
