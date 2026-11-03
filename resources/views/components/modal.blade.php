{{-- Modal enlazado a una propiedad booleana de Livewire: <x-modal wire:model="showForm" title="..."> --}}
@props(['title' => null, 'description' => null, 'maxWidth' => '2xl', 'icon' => null])

@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl', '3xl' => 'sm:max-w-3xl', '4xl' => 'sm:max-w-4xl', '5xl' => 'sm:max-w-5xl'];
    $property = $attributes->wire('model')->value();
@endphp

<div x-data="{ show: $wire.entangle('{{ $property }}') }"
     x-on:keydown.escape.window="show = false"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-[2px]" x-on:click="show = false"></div>

    <div class="flex min-h-full items-end justify-center p-0 sm:items-center sm:p-6">
        <div x-show="show"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
             class="relative w-full {{ $widths[$maxWidth] ?? $widths['2xl'] }} rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
            @if ($title)
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                    <div class="flex items-center gap-3">
                        @if ($icon)
                            <span class="grid size-10 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                                <x-icon :name="$icon" class="size-5" />
                            </span>
                        @endif
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                            @if ($description)
                                <p class="mt-0.5 text-sm text-slate-500">{{ $description }}</p>
                            @endif
                        </div>
                    </div>
                    <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <x-icon name="x" class="size-5" />
                    </button>
                </div>
            @endif

            <div class="max-h-[75vh] overflow-y-auto px-6 py-5 scrollbar-thin">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-wrap items-center justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50/70 px-6 py-3.5">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
