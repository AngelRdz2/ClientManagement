<div>
    <x-page-header :title="$isPromotion ? 'Campañas promocionales' : 'Campañas de cobranza'"
                   :description="$isPromotion ? 'Ofertas comerciales enviadas solo a clientes que autorizaron recibir promociones.' : 'Envíos masivos de cobranza y recordatorios por WhatsApp, segmentados por estado de mora y otros filtros.'">
        <x-slot:actions>
            <x-button icon="plus" :href="route($prefix.'.create')">Nueva campaña</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Campañas" :value="number_format($stats->total)" :icon="$isPromotion ? 'megaphone' : 'hand-coins'" color="indigo" />
        <x-stat-card label="Destinatarios" :value="number_format($stats->recipients)" icon="users" color="blue" />
        <x-stat-card label="Mensajes enviados" :value="number_format($stats->sent)" icon="send" color="green" />
        <x-stat-card label="Mensajes fallidos" :value="number_format($stats->failed)" icon="circle-x" color="red" />
    </div>

    <x-table-wrapper>
        <x-slot:toolbar>
            <div class="flex w-full flex-col gap-2 sm:flex-row">
                <x-input icon="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar campaña…" class="sm:max-w-xs" />
                <x-select wire:model.live="status" class="sm:w-48"><option value="">Todos los estados</option>@foreach (\App\Enums\CampaignStatus::options() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
            </div>
        </x-slot:toolbar>
        <table class="table-base">
            <thead><tr><th>Campaña</th><th>Tipo</th><th>Estado</th><th>Programación</th><th>Destinatarios</th><th>Progreso</th><th>Enviados</th><th>Fallidos</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($campaigns as $c)
                    <tr wire:key="cp-{{ $c->id }}">
                        <td>
                            <a href="{{ route($prefix.'.show', $c) }}" wire:navigate class="font-medium text-slate-900 hover:text-indigo-600">{{ $c->name }}</a>
                            <p class="text-xs text-slate-500">{{ $c->use_status_templates ? 'Plantilla según estado de mora' : ($c->template?->name ?? '—') }}</p>
                        </td>
                        <td><x-badge :color="$c->type->color()">{{ $c->type->label() }}</x-badge></td>
                        <td><x-badge :color="$c->status->color()" dot>{{ $c->status->label() }}</x-badge></td>
                        <td class="text-xs whitespace-nowrap text-slate-600">
                            {{ $c->scheduled_at ? $c->scheduled_at->format('d/m/Y h:i a') : 'Inmediata' }}
                            <span class="block text-slate-400">Creada {{ $c->created_at->format('d/m/Y') }} · {{ $c->creator?->name }}</span>
                        </td>
                        <td class="tabular-nums">{{ number_format($c->total_recipients) }}</td>
                        <td class="w-36">
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-500" style="width: {{ $c->progressPercent() }}%"></div></div>
                            <span class="text-[11px] text-slate-500">{{ $c->progressPercent() }}% · {{ $c->pendingCount() }} pendientes</span>
                        </td>
                        <td class="font-semibold text-emerald-700 tabular-nums">{{ number_format($c->sent_count) }}</td>
                        <td class="font-semibold text-rose-600 tabular-nums">{{ number_format($c->failed_count) }}</td>
                        <td class="text-right"><x-button size="xs" variant="soft" :href="route($prefix.'.show', $c)">Ver</x-button></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty-state :icon="$isPromotion ? 'megaphone' : 'hand-coins'" title="Sin campañas" description="Crea tu primera campaña para enviar mensajes masivos.">
                        <x-button icon="plus" size="sm" :href="route($prefix.'.create')">Nueva campaña</x-button>
                    </x-empty-state></td></tr>
                @endforelse
            </tbody>
        </table>
        <x-slot:footer>{{ $campaigns->links() }}</x-slot:footer>
    </x-table-wrapper>
</div>
