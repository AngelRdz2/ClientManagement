<div>
    <x-page-header title="Historial de mensajes" description="Todos los mensajes de WhatsApp enviados y recibidos, con su estado de entrega." />

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
        @foreach (\App\Enums\MessageStatus::cases() as $s)
            <button wire:click="$set('status', '{{ $status === $s->value ? '' : $s->value }}')" @class(['rounded-xl border bg-white p-3 text-left shadow-card transition', 'border-indigo-400 ring-2 ring-indigo-500/20' => $status === $s->value, 'border-slate-200/80 hover:border-slate-300' => $status !== $s->value])>
                <x-badge :color="$s->color()" size="xs">{{ $s->label() }}</x-badge>
                <p class="mt-1.5 text-xl font-bold text-slate-900 tabular-nums">{{ number_format($stats[$s->value] ?? 0) }}</p>
            </button>
        @endforeach
    </div>

    <x-table-wrapper>
        <x-slot:toolbar>
            <div class="grid w-full grid-cols-2 gap-2 md:grid-cols-6">
                <x-input icon="search" type="search" wire:model.live.debounce.350ms="search" placeholder="Cliente, teléfono o texto…" class="col-span-2" />
                <x-select wire:model.live="direction"><option value="">Dirección</option>@foreach (\App\Enums\MessageDirection::options() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
                <x-select wire:model.live="sender"><option value="">Origen</option>@foreach (\App\Enums\SenderType::options() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
                <x-input type="date" wire:model.live="from" title="Desde" />
                <x-input type="date" wire:model.live="to" title="Hasta" />
                <x-select wire:model.live="campaign" class="col-span-2"><option value="">Todas las campañas</option>@foreach ($campaigns as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</x-select>
            </div>
        </x-slot:toolbar>
        <table class="table-base">
            <thead><tr><th>Fecha</th><th>Cliente / número</th><th>Dirección</th><th>Mensaje</th><th>Origen</th><th>Proveedor</th><th>Estado</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($messages as $m)
                    <tr wire:key="msg-{{ $m->id }}">
                        <td class="text-xs whitespace-nowrap">{{ $m->created_at->format('d/m/Y') }}<span class="block text-slate-400">{{ $m->created_at->format('h:i:s a') }}</span></td>
                        <td>
                            @if ($m->customer)<a href="{{ route('customers.show', $m->customer_id) }}" wire:navigate class="font-medium text-slate-900 hover:text-indigo-600">{{ $m->customer->full_name }}</a>@else<span class="text-slate-500">No identificado</span>@endif
                            <span class="block font-mono text-xs text-slate-500">+{{ $m->phone }}</span>
                        </td>
                        <td><x-icon :name="$m->direction === \App\Enums\MessageDirection::Outbound ? 'arrow-up-right' : 'arrow-down-right'" @class(['size-4', 'text-indigo-500' => $m->direction === \App\Enums\MessageDirection::Outbound, 'text-sky-500' => $m->direction === \App\Enums\MessageDirection::Inbound]) /></td>
                        <td class="max-w-md"><p class="line-clamp-2 text-xs text-slate-600" title="{{ $m->body }}">{{ $m->body }}</p>@if ($m->error_message)<p class="mt-0.5 text-[11px] text-rose-600">{{ $m->error_code }} · {{ $m->error_message }}</p>@endif</td>
                        <td class="text-xs">{{ $m->sender_type->label() }}@if ($m->campaign)<span class="block max-w-[160px] truncate text-slate-400">{{ $m->campaign->name }}</span>@elseif ($m->user)<span class="block text-slate-400">{{ $m->user->name }}</span>@endif</td>
                        <td class="text-xs">@if ($m->provider === 'simulator')<x-badge color="amber" size="xs">Simulado</x-badge>@else{{ ucfirst($m->provider) }}@endif</td>
                        <td><x-badge :color="$m->status->color()">{{ $m->status->label() }}</x-badge></td>
                        <td class="text-right">
                            @if ($m->status === \App\Enums\MessageStatus::Failed && $m->direction === \App\Enums\MessageDirection::Outbound)
                                <button wire:click="retry({{ $m->id }})" class="rounded-lg p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600" title="Reintentar"><x-icon name="refresh-cw" class="size-4" /></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state icon="send" title="Sin mensajes" description="Los mensajes de campañas, del bot y de asesores aparecerán aquí." /></td></tr>
                @endforelse
            </tbody>
        </table>
        <x-slot:footer>{{ $messages->links() }}</x-slot:footer>
    </x-table-wrapper>
</div>
