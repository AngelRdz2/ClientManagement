<div>
    <x-page-header title="Actualizar estados de mora" description="Cambia el estado de uno o varios clientes (queda en el historial) o recalcula automáticamente según las fechas de pago.">
        <x-slot:actions>
            <x-button variant="secondary" icon="history" :href="route('delinquency.history')">Historial de cambios</x-button>
            @can('customers.import')
                <x-button variant="secondary" icon="file-spreadsheet" :href="route('customers.import')">Actualizar por Excel</x-button>
            @endcan
            <x-button variant="dark" icon="refresh-cw"
                      x-on:click="$confirm({ title: '¿Recalcular todos los estados?', text: 'Se calcularán los días de mora de cada cliente activo según la próxima fecha de pago de sus artículos. Los estados manuales se respetan.', confirmText: 'Recalcular' }, () => $wire.recalculateAll())">
                Recalcular por fechas de pago
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-4">
        <div class="min-w-0 xl:col-span-3">
            <x-table-wrapper>
                <x-slot:toolbar>
                    <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-4">
                        <x-input icon="search" type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar cliente…" class="sm:col-span-1" />
                        <x-select wire:model.live="currentStatus">
                            <option value="">Estado actual: todos</option>
                            @foreach ($statuses as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            <option value="none">Sin estado</option>
                        </x-select>
                        <x-input type="number" min="0" wire:model.live.debounce.500ms="daysMin" placeholder="Días desde" />
                        <x-input type="number" min="0" wire:model.live.debounce.500ms="daysMax" placeholder="Días hasta" />
                    </div>
                </x-slot:toolbar>

                <div wire:key="sel-{{ md5(json_encode($pageIds)) }}" x-data="{ pageIds: @js($pageIds) }" class="border-b border-slate-100 bg-slate-50/60 px-4 py-2.5 text-xs text-slate-600">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" class="size-4 rounded border-slate-300 text-indigo-600"
                                   x-bind:checked="pageIds.length && pageIds.every(id => $wire.selected.includes(id))"
                                   x-on:change="$event.target.checked ? $wire.set('selected', [...new Set([...$wire.selected, ...pageIds])]) : $wire.set('selected', $wire.selected.filter(id => ! pageIds.includes(id)))">
                            Seleccionar página
                        </label>
                        @if ($selectAllMatching)
                            <span class="font-semibold text-indigo-700">Se aplicará a los {{ number_format($matching) }} clientes que coinciden con los filtros.</span>
                            <button wire:click="$set('selectAllMatching', false)" class="font-semibold text-slate-500 underline">Cancelar</button>
                        @else
                            <span>{{ count($selected) }} seleccionados</span>
                            @if ($matching > count($selected))
                                <button wire:click="$set('selectAllMatching', true)" class="font-semibold text-indigo-600 hover:underline">Seleccionar los {{ number_format($matching) }} que coinciden</button>
                            @endif
                        @endif
                    </div>
                </div>

                <table class="table-base">
                    <thead><tr><th class="w-10"></th><th>Cliente</th><th>Estado actual</th><th>Días</th><th>Saldo</th><th>Próx. pago</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($customers as $customer)
                            <tr wire:key="u-{{ $customer->id }}">
                                <td><input type="checkbox" value="{{ $customer->id }}" wire:model.live="selected" @disabled($selectAllMatching) class="size-4 rounded border-slate-300 text-indigo-600"></td>
                                <td><a href="{{ route('customers.show', $customer) }}" wire:navigate class="font-medium text-slate-900 hover:text-indigo-600">{{ $customer->full_name }}</a><span class="block text-xs text-slate-500">{{ $customer->code }}</span></td>
                                <td><x-status-pill :status="$customer->delinquencyStatus" /></td>
                                <td class="font-semibold tabular-nums">{{ $customer->days_overdue }}</td>
                                <td class="tabular-nums">{{ money($customer->outstanding_balance) }}</td>
                                <td class="text-xs">{{ $customer->next_payment_date?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state icon="users" title="Sin clientes para los filtros" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
                <x-slot:footer>{{ $customers->links() }}</x-slot:footer>
            </x-table-wrapper>
        </div>

        <div>
            <x-card title="Aplicar nuevo estado" icon="refresh-cw" class="xl:sticky xl:top-20">
                <div class="space-y-4">
                    <x-field label="Nuevo estado" error="targetStatus" required>
                        <x-select wire:model="targetStatus" placeholder="Selecciona…">
                            @foreach ($statuses->where('is_active', true) as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Días de mora" error="targetDays" hint="Déjalo vacío para conservar los días actuales.">
                        <x-input type="number" min="0" wire:model="targetDays" />
                    </x-field>
                    <x-field label="Motivo" error="reason" required>
                        <x-textarea wire:model="reason" rows="3" placeholder="Ej. Conciliación de cartera de noviembre" />
                    </x-field>
                    <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                        Afectará a <strong class="text-slate-900">{{ $selectAllMatching ? number_format($matching) : count($selected) }}</strong> clientes. Cada cambio se registra en el historial con tu usuario.
                    </div>
                    <x-button class="w-full" icon="check" wire:click="apply">Aplicar cambio</x-button>
                </div>
            </x-card>
        </div>
    </div>
</div>
