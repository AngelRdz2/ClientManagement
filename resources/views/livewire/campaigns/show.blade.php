<div @if ($polling) wire:poll.4s @endif>
    <x-page-header :title="$campaign->name" :back="route($prefix.'.index')"
                   :description="$campaign->type->label().' · Creada '.$campaign->created_at->format('d/m/Y h:i a').' por '.($campaign->creator?->name ?? 'sistema')">
        <x-slot:actions>
            <x-badge :color="$campaign->status->color()" dot size="md">{{ $campaign->status->label() }}</x-badge>
            @if ($campaign->isEditable())
                <x-button variant="secondary" size="sm" icon="pencil" :href="route($prefix.'.edit', $campaign)">Editar</x-button>
            @endif
            @if ($campaign->status === \App\Enums\CampaignStatus::Draft)
                <x-button size="sm" icon="send" x-on:click="$confirm({ title: '¿Iniciar campaña?', text: 'Se calcularán los destinatarios con los datos actuales y se enviarán los mensajes.', confirmText: 'Iniciar' }, () => $wire.launch())">Iniciar envío</x-button>
            @endif
            @if (in_array($campaign->status, [\App\Enums\CampaignStatus::Draft, \App\Enums\CampaignStatus::Scheduled, \App\Enums\CampaignStatus::Processing]))
                <x-button variant="ghost" size="sm" icon="circle-x" x-on:click="$confirm({ title: '¿Cancelar campaña?', text: 'Los mensajes pendientes no se enviarán.', danger: true, confirmText: 'Cancelar campaña', cancelText: 'Volver' }, () => $wire.cancel())">Cancelar</x-button>
            @endif
            @if ($campaign->failed_count > 0 && in_array($campaign->status, [\App\Enums\CampaignStatus::Completed, \App\Enums\CampaignStatus::Processing]))
                <x-button variant="warning" size="sm" icon="refresh-cw" wire:click="retryFailed">Reintentar fallidos</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (app(\App\Services\WhatsApp\WhatsAppManager::class)->isSimulation())
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">
            <x-icon name="flask-conical" class="size-4 shrink-0" /> Modo simulación: los mensajes se registran pero <strong>no se envían a WhatsApp real</strong>.
        </div>
    @endif
    @if ($staleQueue)
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <x-icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
            <div>Hay mensajes en cola esperando desde hace más de 2 minutos. Verifica que el procesador de colas esté activo:
                <code class="rounded bg-surface px-1.5 py-0.5 text-xs">php artisan queue:work --queue=campaigns,whatsapp,default</code></div>
        </div>
    @endif
    @if ($campaign->status === \App\Enums\CampaignStatus::Scheduled)
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm text-sky-900">
            <x-icon name="calendar" class="size-4" /> Programada para el <strong>{{ $campaign->scheduled_at->format('d/m/Y \a \l\a\s h:i a') }}</strong>. Requiere el programador de tareas (<code class="text-xs">php artisan schedule:work</code>).
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat-card label="Total de destinatarios" :value="number_format($campaign->total_recipients)" icon="users" color="indigo" />
        <x-stat-card label="Enviados" :value="number_format($campaign->sent_count)" icon="circle-check" color="green" />
        <x-stat-card label="Pendientes" :value="number_format($campaign->pendingCount())" icon="clock" color="slate" />
        <x-stat-card label="Fallidos" :value="number_format($campaign->failed_count)" icon="circle-x" color="red" />
        <x-stat-card label="Omitidos" :value="number_format($campaign->skipped_count)" icon="user-x" color="amber" hint="Reglas de envío / duplicados" />
    </div>

    <div class="mb-6 rounded-2xl border border-slate-200/80 bg-surface p-5 shadow-card">
        <div class="mb-2 flex items-center justify-between text-sm">
            <span class="font-medium text-slate-700">Progreso</span>
            <span class="font-semibold text-slate-900">{{ $campaign->progressPercent() }}%</span>
        </div>
        <div class="flex h-3 overflow-hidden rounded-full bg-slate-100">
            @if ($campaign->total_recipients > 0)
                <div class="bg-emerald-500 transition-all" style="width: {{ $campaign->sent_count / $campaign->total_recipients * 100 }}%"></div>
                <div class="bg-rose-500 transition-all" style="width: {{ $campaign->failed_count / $campaign->total_recipients * 100 }}%"></div>
                <div class="bg-amber-400 transition-all" style="width: {{ $campaign->skipped_count / $campaign->total_recipients * 100 }}%"></div>
            @endif
        </div>
        <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-500">
            <span>Inicio: {{ $campaign->started_at?->format('d/m/Y h:i a') ?? '—' }}</span>
            <span>Fin: {{ $campaign->completed_at?->format('d/m/Y h:i a') ?? '—' }}</span>
            <span>Plantilla: {{ $campaign->use_status_templates ? 'según estado de mora' : ($campaign->template?->name ?? '—') }}</span>
        </div>
    </div>

    <x-table-wrapper>
        <x-slot:toolbar>
            <h3 class="text-sm font-semibold text-slate-900">Resultado por destinatario</h3>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-input icon="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar cliente…" />
                <x-select wire:model.live="recipientStatus" class="sm:w-40"><option value="">Todos</option>@foreach (\App\Enums\RecipientStatus::options() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
            </div>
        </x-slot:toolbar>
        @if ($campaign->total_recipients === 0 && $campaign->status === \App\Enums\CampaignStatus::Draft)
            <x-empty-state icon="users" title="Borrador" description="Los destinatarios se calculan al iniciar el envío, con los datos vigentes en ese momento." />
        @else
            <table class="table-base">
                <thead><tr><th>Cliente</th><th>Estado de mora</th><th>Teléfono</th><th>Mensaje</th><th>Resultado</th><th>Procesado</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recipients as $r)
                        <tr wire:key="r-{{ $r->id }}">
                            <td>@if ($r->customer)<a href="{{ route('customers.show', $r->customer_id) }}" wire:navigate class="font-medium text-slate-900 hover:text-indigo-600">{{ $r->customer->full_name }}</a><span class="block text-xs text-slate-500">{{ $r->customer->code }}</span>@endif</td>
                            <td><x-status-pill :status="$r->customer?->delinquencyStatus" size="xs" /></td>
                            <td class="font-mono text-xs">+{{ $r->phone }}</td>
                            <td class="max-w-sm"><p class="line-clamp-2 text-xs text-slate-600" title="{{ $r->rendered_body }}">{{ $r->rendered_body ?? '—' }}</p></td>
                            <td>
                                <x-badge :color="$r->status->color()">{{ $r->status->label() }}</x-badge>
                                @if ($r->message && in_array($r->message->status, [\App\Enums\MessageStatus::Delivered, \App\Enums\MessageStatus::Read]))
                                    <x-badge :color="$r->message->status->color()" size="xs">{{ $r->message->status->label() }}</x-badge>
                                @endif
                                @if ($r->skip_reason)<p class="mt-0.5 max-w-[220px] text-[11px] text-amber-700">{{ $r->skip_reason }}</p>@endif
                                @if ($r->error_message)<p class="mt-0.5 max-w-[220px] text-[11px] text-rose-600">{{ $r->error_message }}</p>@endif
                            </td>
                            <td class="text-xs whitespace-nowrap text-slate-500">{{ $r->processed_at?->format('d/m h:i a') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="users" title="Sin destinatarios para este filtro" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
        <x-slot:footer>{{ $recipients->links() }}</x-slot:footer>
    </x-table-wrapper>
</div>
