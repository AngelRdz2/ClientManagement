@props(['title' => null, 'description' => null, 'icon' => null, 'padding' => true])

<div {{ $attributes->class(['rounded-2xl border border-slate-200/80 bg-white shadow-card']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex min-w-0 items-center gap-3">
                @if ($icon)
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                        <x-icon :name="$icon" class="size-[18px]" />
                    </span>
                @endif
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-semibold text-slate-900">{{ $title }}</h3>
                    @if ($description)
                        <p class="mt-0.5 text-xs text-slate-500">{{ $description }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</div>
