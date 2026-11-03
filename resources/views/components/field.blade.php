@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->class(['space-y-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="block text-[13px] font-medium text-slate-700">
            {{ $label }} @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($error)
        @error($error)
            <p class="flex items-center gap-1 text-xs text-rose-600"><x-icon name="circle-alert" class="size-3.5 shrink-0" /> {{ $message }}</p>
        @enderror
    @endif
    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
