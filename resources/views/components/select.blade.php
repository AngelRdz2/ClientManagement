@props(['options' => [], 'placeholder' => null, 'error' => null])

@php
    $model = $attributes->wire('model')->value();
    $hasError = $errors->has($error ?? $model ?? '');
@endphp

<select {{ $attributes->class(['form-input pr-8', 'has-error' => $hasError]) }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
    {{ $slot }}
</select>
