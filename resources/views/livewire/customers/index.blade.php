<div>
    <x-page-header title="Clientes" description="Consulta, registra y administra la cartera de clientes.">
        <x-slot:actions>
            @can('customers.import')
                <x-button variant="secondary" icon="file-spreadsheet" :href="route('customers.import')">Importar Excel</x-button>
            @endcan
            @can('customers.manage')
                <x-button icon="user-plus" :href="route('customers.create')">Nuevo cliente</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table-wrapper>
        <x-slot:toolbar>
            <div class="flex w-full flex-col gap-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="flex-1">
                        <x-input icon="search" type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por nombre, DUI, teléfono o código de cliente…" />
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex">
                        <x-select wire:model.live="delinquency" class="sm:w-44">
                            <option value="">Todos los estados de mora</option>
                            @foreach ($this->statuses as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                            <option value="none">Sin estado</option>
                        </x-select>
                        <x-select wire:model.live="status" class="sm:w-36">
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                            <option value="all">Todos</option>
                        </x-select>
                    </div>
                </div>
                <div x-data="{ more: @js($balanceMin !== '' || $balanceMax !== '' || $consent !== '') }" class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div class="flex items-center gap-3 text-slate-500">
                            <span><span class="font-semibold text-slate-800">{{ number_format($totals->total) }}</span> clientes</span>
                            <span class="text-slate-300">|</span>
                            <span>Saldo: <span class="font-semibold text-slate-800">{{ money($totals->balance) }}</span></span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($hasFilters)
                                <x-button variant="ghost" size="sm" icon="x" wire:click="clearFilters">Limpiar filtros</x-button>
                            @endif
                            <x-button variant="secondary" size="sm" icon="filter" x-on:click="more = ! more">Más filtros</x-button>
                        </div>
                    </div>
                    <div x-show="more" x-collapse x-cloak class="grid grid-cols-1 gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-3">
                        <x-field label="Saldo mínimo ($)"><x-input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="balanceMin" placeholder="0.00" /></x-field>
                        <x-field label="Saldo máximo ($)"><x-input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="balanceMax" placeholder="Sin límite" /></x-field>
                        <x-field label="Comunicación">
                            <x-select wire:model.live="consent">
                                <option value="">Todas</option>
                                <option value="promotions">Aceptan promociones</option>
                                <option value="opted_out">Dados de baja de WhatsApp</option>
                            </x-select>
                        </x-field>
                    </div>
                </div>
            </div>
        </x-slot:toolbar>

        <table class="table-base" wire:loading.class="opacity-60">
            <thead>
                <tr>
                    <x-sort-header column="full_name" :$sortBy :$sortDir>Cliente</x-sort-header>
                    <th>DUI / Teléfono</th>
                    <th>Estado de mora</th>
                    <x-sort-header column="days_overdue" :$sortBy :$sortDir>Días</x-sort-header>
                    <x-sort-header column="outstanding_balance" :$sortBy :$sortDir>Saldo pendiente</x-sort-header>
                    <x-sort-header column="next_payment_date" :$sortBy :$sortDir>Próx. pago</x-sort-header>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($customers as $customer)
                    <tr wire:key="c-{{ $customer->id }}">
                        <td>
                            <a href="{{ route('customers.show', $customer) }}" wire:navigate class="group flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-100 to-indigo-200 text-xs font-bold text-indigo-700">
                                    {{ collect(explode(' ', $customer->full_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block max-w-[240px] truncate font-medium text-slate-900 group-hover:text-indigo-600">{{ $customer->full_name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $customer->code }} · {{ $customer->articles_count }} {{ $customer->articles_count === 1 ? 'cuenta' : 'cuentas' }}</span>
                                </span>
                            </a>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="block font-mono text-xs text-slate-700">{{ $customer->dui }}</span>
                            <span class="block text-xs text-slate-500">{{ $customer->phone_primary }}</span>
                        </td>
                        <td><x-status-pill :status="$customer->delinquencyStatus" /></td>
                        <td class="tabular-nums">
                            <span @class(['font-semibold', 'text-rose-600' => $customer->days_overdue >= 60, 'text-amber-600' => $customer->days_overdue > 0 && $customer->days_overdue < 60, 'text-slate-400' => $customer->days_overdue === 0])>{{ $customer->days_overdue }}</span>
                        </td>
                        <td class="font-semibold whitespace-nowrap text-slate-900 tabular-nums">{{ money($customer->outstanding_balance) }}</td>
                        <td class="whitespace-nowrap text-slate-600">{{ $customer->next_payment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            <x-badge :color="$customer->status->color()" dot>{{ $customer->status->label() }}</x-badge>
                            @if ($customer->whatsapp_opted_out_at)
                                <x-badge color="slate" size="xs" class="mt-1" title="Solicitó no recibir mensajes">Baja WA</x-badge>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('customers.show', $customer) }}" wire:navigate class="rounded-lg p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600" title="Ver detalle"><x-icon name="eye" class="size-4" /></a>
                                @can('customers.manage')
                                    <a href="{{ route('customers.edit', $customer) }}" wire:navigate class="rounded-lg p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                    <button type="button" title="{{ $customer->isActive() ? 'Desactivar' : 'Reactivar' }}"
                                            x-on:click="$confirm({ title: @js($customer->isActive() ? '¿Desactivar cliente?' : '¿Reactivar cliente?'), text: @js($customer->full_name.($customer->isActive() ? ' dejará de recibir mensajes y no aparecerá en campañas.' : ' volverá a estar disponible.')), danger: @js($customer->isActive()) }, () => $wire.toggleActive({{ $customer->id }}))"
                                            class="rounded-lg p-1.5 text-slate-400 {{ $customer->isActive() ? 'hover:bg-rose-50 hover:text-rose-600' : 'hover:bg-emerald-50 hover:text-emerald-600' }}">
                                        <x-icon :name="$customer->isActive() ? 'user-x' : 'user-check'" class="size-4" />
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-empty-state icon="users" title="No se encontraron clientes" description="Ajusta la búsqueda o los filtros, o registra un nuevo cliente." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $customers->links() }}</x-slot:footer>
    </x-table-wrapper>
</div>
