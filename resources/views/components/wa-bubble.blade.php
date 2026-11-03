{{-- Burbuja de mensaje estilo WhatsApp --}}
@props(['text' => '', 'outgoing' => true, 'time' => null, 'highlight' => false, 'status' => null, 'label' => null])

<div @class(['flex', 'justify-end' => $outgoing, 'justify-start' => ! $outgoing])>
    <div {{ $attributes->class([
        'relative max-w-[85%] rounded-xl px-3 py-2 text-[13.5px] leading-relaxed text-slate-800 shadow-sm',
        'rounded-tr-sm bg-[#d9fdd3]' => $outgoing,
        'rounded-tl-sm bg-white' => ! $outgoing,
    ]) }}>
        @if ($label)
            <p class="mb-0.5 text-[11px] font-semibold text-indigo-600">{{ $label }}</p>
        @endif
        <div class="break-words whitespace-normal">{!! wa_format($text, $highlight) !!}</div>
        @if ($time || $status)
            <div class="mt-1 flex items-center justify-end gap-1 text-[10.5px] text-slate-500">
                {{ $time }}
                @if ($status === 'read')
                    <span class="text-sky-500">✓✓</span>
                @elseif ($status === 'delivered')
                    <span>✓✓</span>
                @elseif (in_array($status, ['sent', 'queued']))
                    <span>✓</span>
                @elseif ($status === 'pending')
                    <x-icon name="clock" class="size-3" />
                @elseif ($status === 'failed')
                    <x-icon name="circle-alert" class="size-3 text-rose-500" />
                @endif
            </div>
        @endif
    </div>
</div>
