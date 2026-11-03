@props(['label' => null, 'description' => null])

<label class="flex cursor-pointer items-start justify-between gap-4">
    @if ($label)
        <span class="min-w-0">
            <span class="block text-sm font-medium text-slate-800">{{ $label }}</span>
            @if ($description)
                <span class="mt-0.5 block text-xs text-slate-500">{{ $description }}</span>
            @endif
        </span>
    @endif
    <span class="relative mt-0.5 inline-flex shrink-0">
        <input type="checkbox" {{ $attributes->class('peer sr-only') }} />
        <span class="h-5 w-9 rounded-full bg-slate-300 transition peer-checked:bg-indigo-600 peer-focus-visible:ring-4 peer-focus-visible:ring-indigo-500/20"></span>
        <span class="absolute top-0.5 left-0.5 size-4 rounded-full bg-white shadow transition peer-checked:translate-x-4"></span>
    </span>
</label>
