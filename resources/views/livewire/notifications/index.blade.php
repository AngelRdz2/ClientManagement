<div>
    <x-page-header title="Centro de notificaciones" description="Alertas internas del sistema: solicitudes de asesor, campañas, importaciones y errores.">
        <x-slot:actions>
            @if ($unread > 0)
                <x-button variant="secondary" size="sm" icon="check" wire:click="markAllAsRead">Marcar todas como leídas</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-table-wrapper>
        <x-slot:toolbar>
            <div class="inline-flex rounded-lg bg-slate-100 p-1 text-sm">
                @foreach (['all' => 'Todas', 'unread' => "No leídas ($unread)"] as $key => $label)
                    <button wire:click="$set('filter', '{{ $key }}')" @class(['rounded-md px-3 py-1.5 font-medium transition', 'bg-surface text-slate-900 shadow-sm' => $filter === $key, 'text-slate-500 hover:text-slate-700' => $filter !== $key])>{{ $label }}</button>
                @endforeach
            </div>
        </x-slot:toolbar>

        @php
            $tones = ['red' => 'bg-rose-50 text-rose-600', 'green' => 'bg-emerald-50 text-emerald-600', 'amber' => 'bg-amber-50 text-amber-600', 'blue' => 'bg-sky-50 text-sky-600', 'indigo' => 'bg-indigo-50 text-indigo-600', 'violet' => 'bg-violet-50 text-violet-600'];
        @endphp
        <ul class="divide-y divide-slate-100">
            @forelse ($notifications as $notification)
                <li wire:key="n-{{ $notification->id }}" @class(['flex items-start gap-4 px-5 py-4', 'bg-indigo-50/30' => ! $notification->read_at])>
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $tones[$notification->data['color'] ?? 'indigo'] ?? $tones['indigo'] }}">
                        <x-icon :name="($notification->data['icon'] ?? 'bell')" class="size-5" />
                    </span>
                    <button wire:click="open('{{ $notification->id }}')" class="min-w-0 flex-1 text-left">
                        <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            {{ $notification->data['title'] ?? 'Notificación' }}
                            @unless ($notification->read_at) <x-badge color="indigo" size="xs">Nueva</x-badge> @endunless
                        </p>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $notification->data['body'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->format('d/m/Y h:i a') }} · {{ $notification->created_at->diffForHumans() }}</p>
                    </button>
                    <button wire:click="delete('{{ $notification->id }}')" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Eliminar">
                        <x-icon name="trash-2" class="size-4" />
                    </button>
                </li>
            @empty
                <li><x-empty-state icon="bell" title="No hay notificaciones" description="Aquí aparecerán las alertas del sistema." /></li>
            @endforelse
        </ul>

        <x-slot:footer>{{ $notifications->links() }}</x-slot:footer>
    </x-table-wrapper>
</div>
