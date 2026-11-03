@props(['column', 'sortBy', 'sortDir', 'align' => 'left'])

<th {{ $attributes }}>
    <button type="button" wire:click="sort('{{ $column }}')" @class(['inline-flex items-center gap-1 uppercase hover:text-slate-800', 'flex-row-reverse' => $align === 'right'])>
        {{ $slot }}
        @if ($sortBy === $column)
            <x-icon :name="$sortDir === 'asc' ? 'arrow-up-right' : 'arrow-down-right'" class="size-3 text-indigo-500" />
        @else
            <x-icon name="chevron-down" class="size-3 opacity-30" />
        @endif
    </button>
</th>
