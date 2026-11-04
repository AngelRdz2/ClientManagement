<div>
    <x-page-header title="Tablero de clientes" description="Clientes activos agrupados por estado de mora.{{ $canManage ? ' Arrastra una tarjeta a otra columna para cambiar su estado.' : '' }}">
        <x-slot:actions>
            <div class="flex rounded-xl bg-slate-100 p-1 text-sm">
                <a href="{{ route('customers.index') }}" wire:navigate class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium text-slate-500 hover:text-slate-800">
                    <x-icon name="list" class="size-4" /> Lista
                </a>
                <span class="flex items-center gap-1.5 rounded-lg bg-surface px-3 py-1.5 font-medium text-slate-900 shadow-sm">
                    <x-icon name="square-kanban" class="size-4" /> Tablero
                </span>
            </div>
            @can('customers.manage')
                <x-button icon="user-plus" :href="route('customers.create')">Nuevo cliente</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Filtros --}}
    <div class="mb-4 flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-surface p-4 shadow-card xl:flex-row xl:items-center">
        <div class="min-w-0 flex-1">
            <x-input icon="search" type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por nombre, DUI, teléfono o código de cliente…" />
        </div>
        <div class="grid grid-cols-2 gap-2 sm:flex">
            <x-select wire:model.live="group" class="sm:w-48">
                <option value="">Todos los grupos</option>
                @foreach ($this->groups as $g)
                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                @endforeach
            </x-select>
            <x-select wire:model.live="sort" class="sm:w-48">
                <option value="urgency">Más días de mora</option>
                <option value="balance">Mayor saldo</option>
                <option value="name">Nombre (A–Z)</option>
            </x-select>
        </div>
        <div class="flex items-center gap-3 text-sm text-slate-500 xl:border-l xl:border-slate-200 xl:pl-4">
            <span><span class="font-semibold text-slate-800">{{ number_format($summary['total']) }}</span> clientes</span>
            <span class="text-slate-300">|</span>
            <span class="whitespace-nowrap">Saldo: <span class="font-semibold text-slate-800">{{ money($summary['balance']) }}</span></span>
        </div>
    </div>

    {{-- Columnas --}}
    <div class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-4 scrollbar-thin sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" wire:loading.class="opacity-70" wire:target="search,group,sort">
        @foreach ($columns as $column)
            @php $color = $column['status']?->color ?: '#94a3b8'; @endphp
            <section wire:key="col-{{ $column['key'] }}" class="flex max-h-[calc(100vh-15rem)] min-h-64 w-[19rem] shrink-0 flex-col rounded-2xl border border-slate-200/80 bg-slate-100/70">
                <header class="shrink-0 rounded-t-2xl border-t-4 px-3.5 pt-3 pb-2.5" style="border-top-color: {{ $color }}">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="flex min-w-0 items-center gap-2 text-sm font-semibold text-slate-900">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $color }}"></span>
                            <span class="truncate">{{ $column['status']?->name ?? 'Sin estado' }}</span>
                            @if ($column['status']?->is_critical)
                                <span title="Estado crítico"><x-icon name="triangle-alert" class="size-3.5 shrink-0 text-rose-500" /></span>
                            @endif
                        </h2>
                        <span class="rounded-full bg-surface px-2 py-0.5 text-xs font-semibold text-slate-600 tabular-nums shadow-xs">{{ number_format($column['total']) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ money($column['balance']) }} pendiente
                        @if ($column['status'] && ! $column['status']->is_auto_assignable)
                            · <span title="No se asigna ni se recalcula automáticamente">manual</span>
                        @endif
                    </p>
                </header>

                <div class="min-h-24 flex-1 space-y-2 overflow-y-auto px-2 pb-2 scrollbar-thin"
                     @if ($canManage)
                         wire:sort="moveCustomer" wire:sort:group="board" wire:sort:group-id="{{ $column['key'] }}"
                         @unless ($column['status']) wire:sort:config="{ group: { name: 'board', put: false } }" @endunless
                     @endif>
                    @foreach ($column['cards'] as $c)
                        <article wire:key="card-{{ $c->id }}" @if ($canManage) wire:sort:item="{{ $c->id }}" @endif
                                 class="group relative rounded-xl border border-slate-200/80 bg-surface p-3 shadow-card transition hover:border-indigo-300 hover:shadow-elevated {{ $canManage ? 'cursor-grab active:cursor-grabbing' : '' }}">
                            <div class="flex items-start gap-2.5">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-100 to-indigo-200 text-[11px] font-bold text-indigo-700">
                                    {{ collect(explode(' ', $c->full_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('customers.show', $c) }}" wire:navigate class="block truncate text-sm font-semibold text-slate-900 hover:text-indigo-600" wire:sort:ignore>{{ $c->full_name }}</a>
                                    <p class="text-[11px] text-slate-500">{{ $c->code }} · {{ $c->articles_count }} {{ $c->articles_count === 1 ? 'cuenta' : 'cuentas' }}</p>
                                </div>
                                @if ($c->whatsapp_opted_out_at)
                                    <span title="Dado de baja de WhatsApp"><x-icon name="bell-off" class="size-3.5 shrink-0 text-slate-400" /></span>
                                @endif
                            </div>

                            <div class="mt-3 flex items-end justify-between gap-2">
                                <div>
                                    <p class="text-[11px] text-slate-500">Saldo pendiente</p>
                                    <p class="text-base font-bold text-slate-900 tabular-nums">{{ money($c->outstanding_balance) }}</p>
                                </div>
                                <span @class([
                                    'rounded-md px-1.5 py-0.5 text-[11px] font-semibold tabular-nums',
                                    'bg-emerald-50 text-emerald-700' => $c->days_overdue === 0,
                                    'bg-amber-50 text-amber-700' => $c->days_overdue > 0 && $c->days_overdue < 60,
                                    'bg-rose-50 text-rose-700' => $c->days_overdue >= 60,
                                ])>{{ $c->days_overdue === 0 ? 'Al día' : $c->days_overdue.' días' }}</span>
                            </div>

                            <div class="mt-2.5 flex items-center justify-between gap-2 border-t border-slate-100 pt-2 text-[11px] text-slate-500">
                                <span class="flex items-center gap-1" title="Próximo pago">
                                    <x-icon name="calendar-clock" class="size-3.5" /> {{ $c->next_payment_date?->format('d/m/Y') ?? 'Sin fecha' }}
                                </span>
                                @if ($c->phone_primary)
                                    <span class="flex items-center gap-1"><x-icon name="phone" class="size-3.5" /> {{ $c->phone_primary }}</span>
                                @endif
                            </div>
                        </article>
                    @endforeach

                    @if ($column['cards']->isEmpty())
                        <p class="rounded-xl border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-slate-400" wire:sort:ignore>
                            {{ $canManage && $column['status'] ? 'Sin clientes. Suelta aquí una tarjeta.' : 'Sin clientes' }}
                        </p>
                    @endif
                </div>

                @if ($column['cards']->count() < $column['total'])
                    <div class="shrink-0 p-2 pt-0">
                        <button type="button" wire:click="loadMore('{{ $column['key'] }}')" class="flex w-full items-center justify-center gap-1.5 rounded-lg py-1.5 text-xs font-semibold text-indigo-600 transition hover:bg-surface">
                            <x-icon name="chevrons-down" class="size-3.5" /> Ver más ({{ number_format($column['total'] - $column['cards']->count()) }})
                        </button>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    {{-- Confirmación del cambio de estado al soltar una tarjeta --}}
    <x-modal wire:model="showMoveModal" title="Cambiar estado de mora" description="El cambio queda registrado en el historial con el motivo y tu usuario." icon="refresh-cw" maxWidth="lg">
        @if ($move = $this->moving)
            <div class="space-y-4">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-sm font-semibold text-slate-900">{{ $move['customer']->full_name }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <x-status-pill :status="$move['from']" />
                        <x-icon name="arrow-right" class="size-3.5 text-slate-400" />
                        <x-status-pill :status="$move['to']" />
                    </div>
                </div>
                <x-field label="Días de mora" error="moveDays" required><x-input type="number" min="0" wire:model="moveDays" /></x-field>
                <x-field label="Motivo del cambio" error="moveReason" required>
                    <x-textarea wire:model="moveReason" rows="3" placeholder="Ej. Cliente presentó comprobante de pago / acuerdo de pago firmado…" />
                </x-field>
            </div>
        @endif
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="check" wire:click="saveMove">Aplicar cambio</x-button>
        </x-slot:footer>
    </x-modal>
</div>
