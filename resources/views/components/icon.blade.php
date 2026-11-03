{{-- Ícono Lucide: <x-icon name="users" class="size-4" /> (ver App\Support\Icons) --}}
@props(['name'])
{!! \App\Support\Icons::svg($name, $attributes) !!}
