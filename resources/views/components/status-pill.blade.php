{{-- Badge para estados de mora administrables (color hexadecimal definido por el usuario). --}}
@props(['status' => null, 'size' => 'sm'])

@if ($status)
    @php $c = $status->color ?: '#64748b'; @endphp
    <span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full font-medium whitespace-nowrap', $size === 'xs' ? 'px-1.5 py-0.5 text-[11px]' : 'px-2 py-0.5 text-xs']) }}
          style="background-color: {{ $c }}14; color: {{ $c }}; box-shadow: inset 0 0 0 1px {{ $c }}40;">
        <span class="size-1.5 rounded-full" style="background-color: {{ $c }}"></span>
        {{ $status->name }}
    </span>
@else
    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Sin estado</span>
@endif
