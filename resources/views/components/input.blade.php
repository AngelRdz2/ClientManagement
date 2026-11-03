@props(['icon' => null, 'error' => null])

@php
    $model = $attributes->wire('model')->value();
    $hasError = $errors->has($error ?? $model ?? '');
@endphp

@if ($icon)
    <div class="relative">
        <x-icon :name="$icon" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
        <input {{ $attributes->class(['form-input pl-9', 'has-error' => $hasError]) }} />
    </div>
@else
    <input {{ $attributes->class(['form-input', 'has-error' => $hasError]) }} />
@endif
