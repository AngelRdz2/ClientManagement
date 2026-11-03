<div>
    <x-page-header title="Estados de mora" description="Define los estados de cobranza, sus umbrales de días, colores y la plantilla de mensaje que usa cada uno.">
        <x-slot:actions>
            <x-button variant="secondary" icon="history" :href="route('delinquency.history')">Historial de cambios</x-button>
            <x-button icon="plus" wire:click="create">Nuevo estado</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
        <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-indigo-500" />
        <div>
            Los estados se asignan por <strong>importación de Excel</strong>, <strong>cambio manual</strong> o por el <strong>recálculo automático</strong>
            (toma el estado activo y “asignable automáticamente” con mayor umbral de días que no supere los días de mora del cliente).
            Los clientes en estados <em>solventes</em> o <em>excluidos de campañas</em> nunca reciben mensajes de cobranza.
        </div>
    </div>

    <x-table-wrapper>
        <table class="table-base">
            <thead>
                <tr>
                    <th>Prioridad</th><th>Estado</th><th>Días de mora</th><th>Plantilla asociada</th><th>Reglas</th><th>Clientes</th><th>Saldo</th><th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($statuses as $status)
                    <tr wire:key="st-{{ $status->id }}" @class(['opacity-60' => ! $status->is_active])>
                        <td class="font-mono text-xs text-slate-500">#{{ $status->priority }}</td>
                        <td>
                            <x-status-pill :status="$status" />
                            <p class="mt-1 max-w-[260px] text-xs text-slate-500">{{ $status->description }}</p>
                            <p class="font-mono text-[10px] text-slate-400">{{ $status->code }}</p>
                        </td>
                        <td class="whitespace-nowrap">≥ <span class="font-semibold text-slate-800">{{ $status->min_days_overdue }}</span> días</td>
                        <td class="text-xs">
                            @if ($status->is_solvent)
                                <span class="text-slate-400">No enviar mensajes</span>
                            @else
                                {{ $status->messageTemplate?->name ?? '—' }}
                            @endif
                        </td>
                        <td>
                            <div class="flex max-w-[240px] flex-wrap gap-1">
                                @if ($status->is_solvent)<x-badge color="green" size="xs">Solvente</x-badge>@endif
                                @if ($status->is_critical)<x-badge color="red" size="xs">Crítico</x-badge>@endif
                                @if ($status->allows_automatic_messages)<x-badge color="indigo" size="xs">Envío automático</x-badge>@endif
                                @if ($status->exclude_from_campaigns)<x-badge color="amber" size="xs">Excluido de campañas</x-badge>@endif
                                @unless ($status->is_auto_assignable)<x-badge color="violet" size="xs">Asignación manual</x-badge>@endunless
                                @unless ($status->is_active)<x-badge color="slate" size="xs">Inactivo</x-badge>@endunless
                            </div>
                        </td>
                        <td class="font-semibold tabular-nums">{{ number_format($status->customers_count) }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ money($status->customers_sum_outstanding_balance) }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button wire:click="edit({{ $status->id }})" class="rounded-lg p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600" title="Editar"><x-icon name="pencil" class="size-4" /></button>
                                <button x-on:click="$confirm({ title: '¿Eliminar estado?', text: @js($status->name), danger: true, confirmText: 'Eliminar' }, () => $wire.delete({{ $status->id }}))" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Eliminar"><x-icon name="trash-2" class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state icon="gauge" title="Sin estados de mora" description="Crea el primer estado de cobranza." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-table-wrapper>

    <x-modal wire:model="showModal" :title="$editingId ? 'Editar estado de mora' : 'Nuevo estado de mora'" icon="gauge" maxWidth="2xl">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
            <x-field label="Nombre" error="form.name" required class="sm:col-span-4"><x-input wire:model.live.debounce.400ms="form.name" placeholder="Ej. Mora 45 días" /></x-field>
            <x-field label="Código (importación)" error="form.code" required class="sm:col-span-2"><x-input wire:model="form.code" class="font-mono uppercase" /></x-field>
            <x-field label="Descripción" error="form.description" class="sm:col-span-6"><x-input wire:model="form.description" /></x-field>
            <x-field label="Días de mora (desde)" error="form.min_days_overdue" required class="sm:col-span-2"><x-input type="number" min="0" wire:model="form.min_days_overdue" /></x-field>
            <x-field label="Prioridad" error="form.priority" required class="sm:col-span-2" hint="Orden de visualización"><x-input type="number" min="0" wire:model="form.priority" /></x-field>
            <x-field label="Color" error="form.color" required class="sm:col-span-2">
                <div class="flex items-center gap-2">
                    <input type="color" wire:model.live="form.color" class="h-9 w-12 cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                    <x-input wire:model.live="form.color" class="font-mono" />
                </div>
            </x-field>
            <x-field label="Plantilla de mensaje asociada" error="form.message_template_id" class="sm:col-span-6" hint="Se usa en campañas de cobranza con “plantilla según estado de mora”.">
                <x-select wire:model="form.message_template_id" placeholder="Sin plantilla">
                    @foreach ($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                </x-select>
            </x-field>
            <div class="grid grid-cols-1 gap-4 rounded-xl bg-slate-50 p-4 sm:col-span-6 sm:grid-cols-2">
                <x-toggle wire:model="form.allows_automatic_messages" label="Permite envíos automáticos" description="Se incluye en campañas automáticas de cobranza." />
                <x-toggle wire:model="form.exclude_from_campaigns" label="Excluir de campañas" description="Nunca recibe campañas masivas." />
                <x-toggle wire:model="form.is_solvent" label="Estado solvente" description="Cliente al día: no recibe cobranza." />
                <x-toggle wire:model="form.is_critical" label="Mora crítica" description="Se contabiliza como crítica en el dashboard." />
                <x-toggle wire:model="form.is_auto_assignable" label="Asignable automáticamente" description="El recálculo por días puede asignarlo." />
                <x-toggle wire:model="form.is_active" label="Activo" description="Disponible para asignar a clientes." />
            </div>
            <div class="flex items-center gap-3 sm:col-span-6">
                <span class="text-xs text-slate-500">Vista previa:</span>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium" style="background-color: {{ $form['color'] ?? '#64748b' }}14; color: {{ $form['color'] ?? '#64748b' }}; box-shadow: inset 0 0 0 1px {{ $form['color'] ?? '#64748b' }}40;">
                    <span class="size-1.5 rounded-full" style="background-color: {{ $form['color'] ?? '#64748b' }}"></span>{{ ($form['name'] ?? '') ?: 'Nombre del estado' }}
                </span>
            </div>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="check" wire:click="save">Guardar estado</x-button>
        </x-slot:footer>
    </x-modal>
</div>
