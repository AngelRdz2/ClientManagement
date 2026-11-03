<div class="relative w-full max-w-md flex-1" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false"
     x-on:keydown.window.prevent.ctrl.k="$refs.search.focus()">
    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
    <input x-ref="search" type="search" wire:model.live.debounce.300ms="query" x-on:focus="open = true" x-on:input="open = true"
           placeholder="Buscar cliente por nombre, DUI, teléfono o código…"
           class="w-full rounded-xl border-0 bg-slate-100/80 py-2 pr-14 pl-9 text-sm text-slate-800 placeholder:text-slate-400 transition focus:bg-white focus:ring-2 focus:ring-indigo-500/30 focus:outline-none" />
    <kbd class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded border border-slate-300 bg-white px-1.5 text-[10px] font-semibold text-slate-400 md:block">Ctrl K</kbd>

    <div x-show="open && $wire.query.length >= 2" x-cloak x-transition
         class="absolute top-full right-0 left-0 z-40 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-elevated">
        <div wire:loading.flex class="items-center gap-2 px-4 py-3 text-xs text-slate-500">
            <x-icon name="loader-circle" class="size-4 animate-spin" /> Buscando…
        </div>
        <div wire:loading.remove>
            @forelse ($results as $customer)
                <a href="{{ route('customers.show', $customer) }}" wire:navigate x-on:click="open = false"
                   class="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 last:border-0 hover:bg-indigo-50/50">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                        {{ mb_substr($customer->full_name, 0, 1) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-slate-800">{{ $customer->full_name }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $customer->code }} · DUI {{ $customer->dui }} · {{ $customer->phone_primary }}</span>
                    </span>
                    <x-status-pill :status="$customer->delinquencyStatus" size="xs" />
                </a>
            @empty
                <p class="px-4 py-6 text-center text-sm text-slate-500">No se encontraron clientes para “{{ $query }}”.</p>
            @endforelse
        </div>
    </div>
</div>
