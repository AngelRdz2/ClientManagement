@php $status = $secretStatus[$key]; $model = 'secrets.'.str_replace('.', '__', $key); @endphp
<x-field :label="$label">
    @if ($status['env'])
        <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800"><x-icon name="lock" class="size-3.5" /> Definido en variables de entorno (.env)</div>
    @else
        <div class="flex gap-2">
            <x-input type="password" wire:model="{{ $model }}" autocomplete="off" :placeholder="$status['set'] ? '•••••••• (guardado, cifrado)' : 'No configurado'" class="font-mono" />
            @if ($status['set'])
                <button type="button" title="Eliminar credencial" x-on:click="$confirm({ title: '¿Eliminar credencial?', danger: true }, () => $wire.clearSecret('{{ $key }}'))" class="rounded-lg px-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><x-icon name="trash-2" class="size-4" /></button>
            @endif
        </div>
    @endif
</x-field>
