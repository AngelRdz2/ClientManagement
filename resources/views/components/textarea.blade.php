@props(['error' => null])

@php
    $model = $attributes->wire('model')->value();
    $hasError = $errors->has($error ?? $model ?? '');
@endphp

<textarea {{ $attributes->class(['form-input min-h-[90px]', 'has-error' => $hasError])->merge(['rows' => 3]) }}>{{ $slot }}</textarea>
