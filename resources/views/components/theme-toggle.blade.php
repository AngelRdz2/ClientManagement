{{-- Selector de tema: claro, oscuro o según el sistema (la preferencia se guarda en el navegador) --}}
<x-dropdown width="w-44">
    <x-slot:trigger>
        <button type="button" class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" title="Tema">
            <x-icon name="sun" class="size-5 dark:hidden" />
            <x-icon name="moon" class="hidden size-5 dark:block" />
        </button>
    </x-slot:trigger>
    <p class="px-3.5 pt-1 pb-1.5 text-[11px] font-semibold tracking-wide text-slate-400 uppercase">Tema</p>
    @foreach (['light' => ['sun', 'Claro'], 'dark' => ['moon', 'Oscuro'], 'system' => ['monitor', 'Sistema']] as $value => [$icon, $label])
        <x-dropdown-item :icon="$icon" x-on:click="$store.theme.set('{{ $value }}')"
                         ::class="$store.theme.preference === '{{ $value }}' && 'font-semibold'">
            {{ $label }}
            <x-icon name="check" class="ml-auto size-4 text-indigo-600" x-show="$store.theme.preference === '{{ $value }}'" />
        </x-dropdown-item>
    @endforeach
</x-dropdown>
