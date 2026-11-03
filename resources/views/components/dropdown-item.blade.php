@props(['icon' => null, 'href' => null, 'danger' => false])

@php $classes = 'flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-sm transition '.($danger ? 'text-rose-600 hover:bg-rose-50' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'); @endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->class($classes) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4 opacity-70" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->class($classes) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4 opacity-70" /> @endif
        {{ $slot }}
    </button>
@endif
