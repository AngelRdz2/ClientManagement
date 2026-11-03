@props(['title', 'description' => null, 'back' => null])

<div {{ $attributes->class(['mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-indigo-600">
                <x-icon name="chevron-left" class="size-3.5" /> Volver
            </a>
        @endif
        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
