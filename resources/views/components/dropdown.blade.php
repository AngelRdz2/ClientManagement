@props(['align' => 'right', 'width' => 'w-56'])

<div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" class="relative">
    <div x-on:click="open = ! open">{{ $trigger }}</div>
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         x-on:click="open = false"
         @class([
             'absolute z-40 mt-2 origin-top-right rounded-xl border border-slate-200 bg-white py-1.5 shadow-elevated',
             $width,
             'right-0' => $align === 'right',
             'left-0' => $align === 'left',
         ])>
        {{ $slot }}
    </div>
</div>
