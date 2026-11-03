@props(['color' => 'slate', 'dot' => false, 'size' => 'sm'])

@php
    $colors = [
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'red' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/25',
        'blue' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    ];
    $dots = [
        'green' => 'bg-emerald-500', 'red' => 'bg-rose-500', 'amber' => 'bg-amber-500', 'blue' => 'bg-sky-500',
        'indigo' => 'bg-indigo-500', 'violet' => 'bg-violet-500', 'slate' => 'bg-slate-400',
    ];
    $sizes = ['xs' => 'px-1.5 py-0.5 text-[11px]', 'sm' => 'px-2 py-0.5 text-xs', 'md' => 'px-2.5 py-1 text-xs'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full font-medium ring-1 ring-inset whitespace-nowrap', $colors[$color] ?? $colors['slate'], $sizes[$size] ?? $sizes['sm']]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dots[$color] ?? $dots['slate'] }}"></span>
    @endif
    {{ $slot }}
</span>
