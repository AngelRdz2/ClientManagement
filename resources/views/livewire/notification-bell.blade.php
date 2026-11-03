<div wire:poll.20s class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <button x-on:click="open = ! open" class="relative rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" title="Notificaciones">
        <x-icon name="bell" class="size-5" />
        @if ($unreadCount > 0)
            <span class="absolute top-1 right-1 grid min-w-[17px] place-items-center rounded-full bg-rose-500 px-1 text-[10px] leading-[17px] font-bold text-white ring-2 ring-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute right-0 z-40 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-elevated">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-semibold text-slate-900">Notificaciones</p>
            @if ($unreadCount > 0)
                <button wire:click="markAllAsRead" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Marcar todas como leídas</button>
            @endif
        </div>
        <div class="max-h-96 overflow-y-auto scrollbar-thin">
            @php
                $tones = ['red' => 'bg-rose-50 text-rose-600', 'green' => 'bg-emerald-50 text-emerald-600', 'amber' => 'bg-amber-50 text-amber-600', 'blue' => 'bg-sky-50 text-sky-600', 'indigo' => 'bg-indigo-50 text-indigo-600', 'violet' => 'bg-violet-50 text-violet-600'];
            @endphp
            @forelse ($notifications as $notification)
                <button wire:click="markAsRead('{{ $notification->id }}')" x-on:click="open = false"
                        @class(['flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left transition last:border-0 hover:bg-slate-50', 'bg-indigo-50/40' => ! $notification->read_at])>
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $tones[$notification->data['color'] ?? 'indigo'] ?? $tones['indigo'] }}">
                        <x-icon :name="($notification->data['icon'] ?? 'bell')" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="truncate text-sm font-medium text-slate-800">{{ $notification->data['title'] ?? 'Notificación' }}</span>
                            @unless ($notification->read_at)<span class="size-1.5 shrink-0 rounded-full bg-indigo-500"></span>@endunless
                        </span>
                        <span class="mt-0.5 line-clamp-2 block text-xs text-slate-500">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="mt-1 block text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <x-empty-state icon="bell" title="Sin notificaciones" description="Te avisaremos cuando ocurra algo importante." class="py-8" />
            @endforelse
        </div>
        <a href="{{ route('notifications.index') }}" wire:navigate class="block border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 text-center text-xs font-semibold text-indigo-600 hover:text-indigo-500">
            Ver todas las notificaciones
        </a>
    </div>
</div>
