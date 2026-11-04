@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'icon' => null, 'iconRight' => null, 'type' => 'button', 'navigate' => true])

@php
    $variants = [
        'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-indigo-600',
        'dark' => 'bg-navy-900 text-white shadow-sm hover:bg-navy-800 focus-visible:outline-navy-900 dark:ring-1 dark:ring-white/10',
        'secondary' => 'bg-surface text-slate-700 shadow-xs ring-1 ring-inset ring-slate-300 hover:bg-slate-50',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-500 focus-visible:outline-rose-600',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-emerald-600',
        'warning' => 'bg-amber-500 text-white shadow-sm hover:bg-amber-400 focus-visible:outline-amber-500',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        'soft' => 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100',
    ];
    $sizes = [
        'xs' => 'gap-1 rounded-md px-2 py-1 text-xs',
        'sm' => 'gap-1.5 rounded-lg px-2.5 py-1.5 text-xs',
        'md' => 'gap-2 rounded-lg px-3.5 py-2 text-sm',
        'lg' => 'gap-2 rounded-xl px-4 py-2.5 text-sm',
    ];
    $iconSize = in_array($size, ['xs', 'sm']) ? 'size-3.5' : 'size-4';
    $classes = 'inline-flex items-center justify-center font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:pointer-events-none '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes->class($classes) }}>
        @if ($icon) <x-icon :name="$icon" class="{{ $iconSize }} shrink-0" /> @endif
        {{ $slot }}
        @if ($iconRight) <x-icon :name="$iconRight" class="{{ $iconSize }} shrink-0" /> @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            @if ($attributes->has('wire:click') || $attributes->has('wire:target'))
                <x-icon name="loader-circle" class="{{ $iconSize }} shrink-0 animate-spin" wire:loading wire:target="{{ $attributes->get('wire:target', \Illuminate\Support\Str::before($attributes->get('wire:click', ''), '(')) }}" />
                <x-icon :name="$icon" class="{{ $iconSize }} shrink-0" wire:loading.remove wire:target="{{ $attributes->get('wire:target', \Illuminate\Support\Str::before($attributes->get('wire:click', ''), '(')) }}" />
            @else
                <x-icon :name="$icon" class="{{ $iconSize }} shrink-0" />
            @endif
        @endif
        {{ $slot }}
        @if ($iconRight) <x-icon :name="$iconRight" class="{{ $iconSize }} shrink-0" /> @endif
    </button>
@endif
