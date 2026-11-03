@props(['footer' => null])

<div {{ $attributes->class(['overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-card']) }}>
    @isset($toolbar)
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            {{ $toolbar }}
        </div>
    @endisset
    <div class="overflow-x-auto scrollbar-thin">
        {{ $slot }}
    </div>
    @if ($footer)
        <div class="border-t border-slate-100 px-4 py-3">{{ $footer }}</div>
    @endif
</div>
