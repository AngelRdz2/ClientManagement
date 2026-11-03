<div>
    <x-page-header title="Grupos de clientes" description="Agrupa clientes para segmentar campañas de cobranza y promociones.">
        <x-slot:actions><x-button icon="plus" wire:click="create">Nuevo grupo</x-button></x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($groups as $group)
            <div wire:key="g-{{ $group->id }}" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-card">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl text-white" style="background-color: {{ $group->color }}"><x-icon name="layers" class="size-5" /></span>
                        <div><p class="font-semibold text-slate-900">{{ $group->name }}</p><p class="text-xs text-slate-500">{{ number_format($group->customers_count) }} clientes</p></div>
                    </div>
                    <div class="flex gap-1">
                        <button wire:click="edit({{ $group->id }})" class="rounded-lg p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600"><x-icon name="pencil" class="size-4" /></button>
                        <button x-on:click="$confirm({ title: '¿Eliminar grupo?', text: @js($group->name.' (los clientes no se eliminan)'), danger: true }, () => $wire.delete({{ $group->id }}))" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><x-icon name="trash-2" class="size-4" /></button>
                    </div>
                </div>
                <p class="mt-3 min-h-[2.5rem] text-sm text-slate-600">{{ $group->description ?: 'Sin descripción' }}</p>
                <x-button variant="soft" size="sm" icon="users" class="mt-3 w-full" wire:click="manageMembers({{ $group->id }})">Administrar miembros</x-button>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white"><x-empty-state icon="layers" title="Sin grupos" description="Crea grupos para segmentar tus campañas." /></div>
        @endforelse
    </div>

    <x-modal wire:model="showModal" :title="$editingId ? 'Editar grupo' : 'Nuevo grupo'" icon="layers" maxWidth="md">
        <div class="space-y-4">
            <x-field label="Nombre" error="name" required><x-input wire:model="name" /></x-field>
            <x-field label="Descripción" error="description"><x-textarea wire:model="description" rows="2" /></x-field>
            <x-field label="Color" error="color"><div class="flex items-center gap-2"><input type="color" wire:model.live="color" class="h-9 w-12 rounded-lg border border-slate-300 p-1"><x-input wire:model.live="color" class="font-mono" /></div></x-field>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="check" wire:click="save">Guardar</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showMembers" :title="'Miembros: '.($membersGroup?->name ?? '')" icon="users" maxWidth="xl">
        <x-input icon="search" type="search" wire:model.live.debounce.300ms="memberSearch" placeholder="Buscar cliente para agregar…" />
        @if ($candidates->isNotEmpty())
            <ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200">
                @foreach ($candidates as $c)
                    <li class="flex items-center justify-between px-3 py-2 text-sm"><span>{{ $c->full_name }} <span class="text-xs text-slate-400">{{ $c->code }}</span></span><x-button size="xs" variant="soft" icon="plus" wire:click="addMember({{ $c->id }})">Agregar</x-button></li>
                @endforeach
            </ul>
        @endif
        <p class="mt-4 mb-2 text-xs font-semibold text-slate-500 uppercase">Miembros actuales ({{ $members->count() }})</p>
        <ul class="max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 scrollbar-thin">
            @forelse ($members as $m)
                <li wire:key="mem-{{ $m->id }}" class="flex items-center justify-between px-3 py-2 text-sm"><span>{{ $m->full_name }} <span class="text-xs text-slate-400">{{ $m->code }}</span></span><button wire:click="removeMember({{ $m->id }})" class="rounded p-1 text-slate-400 hover:text-rose-600"><x-icon name="x" class="size-4" /></button></li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-slate-500">Este grupo no tiene clientes.</li>
            @endforelse
        </ul>
    </x-modal>
</div>
