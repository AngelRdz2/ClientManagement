@props(['icon' => 'inbox', 'title' => 'Sin resultados', 'description' => null])

<div {{ $attributes->class(['flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="grid size-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="size-7" />
    </span>
    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
