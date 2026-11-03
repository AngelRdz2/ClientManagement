<div wire:poll.15s>
    @if ($total > 0)
        <div class="mb-6 overflow-hidden rounded-2xl border border-rose-200 bg-gradient-to-r from-rose-50 to-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="relative grid size-10 place-items-center rounded-xl bg-rose-600 text-white">
                        <x-icon name="headset" class="size-5" />
                        <span class="absolute -top-1 -right-1 flex size-3">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex size-3 rounded-full bg-rose-500 ring-2 ring-white"></span>
                        </span>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-rose-900">
                            {{ $total }} {{ $total === 1 ? 'cliente solicita' : 'clientes solicitan' }} hablar con un asesor
                        </p>
                        <p class="text-xs text-rose-700/80">El bot dejó de responder en estas conversaciones. Toma una para atenderla.</p>
                    </div>
                </div>
                @if (Route::has('conversations.index'))
                    <x-button variant="danger" size="sm" icon="messages-square" :href="route('conversations.index', ['filter' => 'pending'])">Ir a la bandeja</x-button>
                @endif
            </div>
            <div class="flex gap-3 overflow-x-auto border-t border-rose-100 px-5 py-3 scrollbar-thin">
                @foreach ($pending as $conversation)
                    <a href="{{ Route::has('conversations.index') ? route('conversations.index', ['c' => $conversation->id]) : '#' }}" wire:navigate
                       class="flex min-w-[220px] items-center gap-3 rounded-xl border border-rose-100 bg-white px-3 py-2 transition hover:border-rose-300">
                        <span class="grid size-8 place-items-center rounded-full bg-rose-100 text-xs font-bold text-rose-700">
                            {{ mb_substr($conversation->displayName(), 0, 1) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-slate-800">{{ $conversation->displayName() }}</span>
                            <span class="block text-xs text-slate-500">Esperando {{ $conversation->agent_requested_at?->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
