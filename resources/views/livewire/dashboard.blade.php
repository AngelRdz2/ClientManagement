<div>
    <x-page-header title="Dashboard" description="Resumen general de clientes, cobranza y comunicación por WhatsApp.">
        <x-slot:actions>
            <span class="hidden text-xs text-slate-500 sm:inline">Actualizado {{ now()->format('d/m/Y h:i a') }}</span>
            <x-button variant="secondary" size="sm" icon="refresh-cw" wire:click="refresh">Actualizar</x-button>
            @if (Route::has('customers.create') && auth()->user()->can('customers.manage'))
                <x-button size="sm" icon="user-plus" :href="route('customers.create')">Nuevo cliente</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Alerta de solicitudes de asesor (se actualiza automáticamente) --}}
    @can('conversations.attend')
        <livewire:dashboard.agent-alerts />
    @endcan

    {{-- KPIs de clientes y cartera --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total de clientes" :value="number_format($kpis['customers_total'])" icon="users" color="indigo"
                     :hint="number_format($breakdown['inactive']).' inactivos no incluidos'" :href="Route::has('customers.index') ? route('customers.index') : null" />
        <x-stat-card label="Clientes solventes" :value="number_format($kpis['customers_solvent'])" icon="circle-check" color="green"
                     :hint="$kpis['customers_total'] ? round($kpis['customers_solvent'] / $kpis['customers_total'] * 100, 1).'% de la cartera' : 'Sin clientes'" />
        <x-stat-card label="Clientes en mora" :value="number_format($kpis['customers_delinquent'])" icon="clock" color="amber"
                     :hint="$kpis['customers_total'] ? round($kpis['customers_delinquent'] / $kpis['customers_total'] * 100, 1).'% de la cartera' : 'Sin clientes'" />
        <x-stat-card label="Mora crítica" :value="number_format($kpis['customers_critical'])" icon="triangle-alert" color="red"
                     hint="Requieren gestión prioritaria" />
        <x-stat-card label="Saldo pendiente por cobrar" :value="money($kpis['outstanding_balance'])" icon="wallet" color="navy"
                     hint="Suma de saldos de clientes activos" />
        <x-stat-card label="Cuentas pendientes" :value="number_format($kpis['pending_accounts'])" icon="receipt" color="blue"
                     hint="Artículos con saldo por cobrar" />
        <x-stat-card label="Conversaciones activas" :value="number_format($kpis['conversations_active'])" icon="messages-square" color="violet"
                     hint="Abiertas en la bandeja de WhatsApp" :href="Route::has('conversations.index') ? route('conversations.index') : null" />
        <x-stat-card label="Solicitudes de asesor" :value="number_format($kpis['agent_requests'])" icon="headset" color="red"
                     hint="Clientes esperando a una persona" :alert="$kpis['agent_requests'] > 0"
                     :href="Route::has('conversations.index') ? route('conversations.index', ['filter' => 'pending']) : null" />
    </div>

    {{-- KPIs de mensajería --}}
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-surface p-4 shadow-card">
            <span class="grid size-11 place-items-center rounded-xl bg-indigo-50 text-indigo-600"><x-icon name="send" class="size-5" /></span>
            <div>
                <p class="text-xs font-medium text-slate-500">Mensajes enviados este mes</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums">{{ number_format($kpis['messages_sent_month']) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-surface p-4 shadow-card">
            <span class="grid size-11 place-items-center rounded-xl bg-slate-100 text-slate-600"><x-icon name="clock" class="size-5" /></span>
            <div>
                <p class="text-xs font-medium text-slate-500">Mensajes pendientes</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums">{{ number_format($kpis['messages_pending']) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-surface p-4 shadow-card">
            <span class="grid size-11 place-items-center rounded-xl bg-rose-50 text-rose-600"><x-icon name="circle-x" class="size-5" /></span>
            <div>
                <p class="text-xs font-medium text-slate-500">Mensajes fallidos este mes</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums">{{ number_format($kpis['messages_failed_month']) }}</p>
            </div>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card title="Distribución por estado de mora" description="Clientes activos" icon="chart-pie" class="xl:col-span-1">
            @if (array_sum($distribution['counts']) > 0)
                <div wire:ignore x-data="chart(@js($charts['distribution']))" class="h-72"><canvas x-ref="canvas"></canvas></div>
            @else
                <x-empty-state icon="chart-pie" title="Sin datos" description="Aún no hay clientes registrados." />
            @endif
        </x-card>

        <x-card title="Evolución de cobranza" description="Pagos registrados en los últimos 6 meses" icon="trending-up" class="xl:col-span-2">
            <div wire:ignore x-data="chart(@js($charts['collection']))" class="h-72"><canvas x-ref="canvas"></canvas></div>
        </x-card>

        <x-card title="Mensajes enviados por período" description="Últimos 14 días" icon="chart-column" class="xl:col-span-2">
            <div wire:ignore x-data="chart(@js($charts['messages']))" class="h-72"><canvas x-ref="canvas"></canvas></div>
        </x-card>

        <x-card title="Saldo por estado de mora" description="Monto pendiente de clientes activos" icon="wallet">
            <div wire:ignore x-data="chart(@js($charts['balanceByStatus']))" class="h-72"><canvas x-ref="canvas"></canvas></div>
        </x-card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Clientes por estado --}}
        <x-card title="Clientes por estado" icon="list-checks" :padding="false">
            <ul class="divide-y divide-slate-100">
                @foreach ($distribution['rows'] as $status)
                    @php $pct = $kpis['customers_total'] ? $status->customers_count / $kpis['customers_total'] * 100 : 0; @endphp
                    <li class="px-5 py-3">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <x-status-pill :status="$status" />
                            <span class="font-semibold text-slate-900 tabular-nums">{{ number_format($status->customers_count) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ $pct }}%; background-color: {{ $status->color }}"></div>
                        </div>
                    </li>
                @endforeach
                <li class="grid grid-cols-2 gap-4 bg-slate-50/60 px-5 py-3 text-xs">
                    <div><p class="text-slate-500">Aceptan promociones</p><p class="text-sm font-semibold text-slate-900">{{ number_format($breakdown['promo_consent']) }}</p></div>
                    <div><p class="text-slate-500">Dados de baja (WhatsApp)</p><p class="text-sm font-semibold text-slate-900">{{ number_format($breakdown['opted_out']) }}</p></div>
                </li>
            </ul>
        </x-card>

        {{-- Actividad reciente --}}
        <x-card title="Actividad reciente" icon="activity" class="xl:col-span-2" :padding="false">
            @forelse ($activity as $log)
                <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
                        <x-icon :name="match (\Illuminate\Support\Str::before($log->action, '.')) {
                            'customer' => 'user-round', 'import' => 'file-spreadsheet', 'campaign' => 'megaphone', 'conversation' => 'messages-square',
                            'status' => 'refresh-cw', 'template' => 'file-text', 'auth' => 'log-in', 'user' => 'user-cog', 'settings' => 'settings',
                            default => 'activity' }" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-700">{{ $log->description }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">{{ $log->user?->name ?? 'Sistema' }} · {{ $log->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <x-empty-state icon="activity" title="Sin actividad registrada" description="Las acciones importantes del sistema aparecerán aquí." />
            @endforelse
        </x-card>
    </div>
</div>
