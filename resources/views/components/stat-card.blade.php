@props(['label', 'value', 'icon' => 'activity', 'color' => 'indigo', 'hint' => null, 'href' => null, 'alert' => false])

@php
    $tones = [
        'indigo' => 'bg-indigo-50 text-indigo-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'red' => 'bg-rose-50 text-rose-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'blue' => 'bg-sky-50 text-sky-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'slate' => 'bg-slate-100 text-slate-600',
        'navy' => 'bg-navy-50 text-navy-700',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class([
        'group relative flex flex-col justify-between overflow-hidden rounded-2xl border bg-surface p-5 shadow-card transition',
        'border-rose-200 ring-4 ring-rose-500/10' => $alert,
        'border-slate-200/80' => ! $alert,
        'hover:-translate-y-0.5 hover:shadow-elevated' => $href,
    ]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-[13px] font-medium text-slate-500">{{ $label }}</p>
        <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $tones[$color] ?? $tones['indigo'] }}">
            <x-icon :name="$icon" class="size-[18px]" />
        </span>
    </div>
    <div class="mt-3">
        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
        @endif
    </div>
    @if ($alert)
        <span class="absolute top-3 right-14 flex size-2.5">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
            <span class="relative inline-flex size-2.5 rounded-full bg-rose-500"></span>
        </span>
    @endif
</{{ $tag }}>
