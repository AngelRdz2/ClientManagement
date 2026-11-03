<div>
    <x-page-header :title="$campaignId ? 'Editar campaña' : ($isPromotion ? 'Nueva campaña promocional' : 'Nueva campaña de cobranza')"
                   description="Define la audiencia, elige la plantilla y revisa cómo quedará el mensaje antes de enviarlo." :back="route($prefix.'.index')" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div class="space-y-6 xl:col-span-3">
            {{-- 1. Datos generales --}}
            <x-card title="1. Datos de la campaña" icon="megaphone">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Nombre" error="name" required class="sm:col-span-2"><x-input wire:model="name" :placeholder="$isPromotion ? 'Ej. Promoción Navidad sin prima' : 'Ej. Cobranza noviembre - mora 30 y 60'" /></x-field>
                    @unless ($isPromotion)
                        <x-field label="Tipo de campaña" error="type">
                            <x-select wire:model.live="type">@foreach ($types as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</x-select>
                        </x-field>
                    @endunless
                    <x-field label="Envío" error="when" @class(['sm:col-span-2' => $isPromotion])>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['now' => ['Enviar al confirmar', 'send'], 'later' => ['Programar', 'calendar']] as $value => [$label, $icon])
                                <label @class(['flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm', 'border-indigo-500 bg-indigo-50 text-indigo-700' => $when === $value, 'border-slate-300 text-slate-600' => $when !== $value])>
                                    <input type="radio" value="{{ $value }}" wire:model.live="when" class="sr-only"><x-icon :name="$icon" class="size-4" /> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </x-field>
                    @if ($when === 'later')
                        <x-field label="Fecha y hora de envío" error="scheduled_at" required class="sm:col-span-2"><x-input type="datetime-local" wire:model="scheduled_at" /></x-field>
                    @endif
                </div>
            </x-card>

            {{-- 2. Segmentación --}}
            <x-card title="2. Destinatarios" description="Combina filtros; solo se enviará a clientes que cumplan las reglas de comunicación." icon="filter">
                <x-slot:actions><x-button variant="ghost" size="xs" icon="x" wire:click="resetFilters">Limpiar</x-button></x-slot:actions>
                <div class="space-y-5">
                    <div>
                        <p class="mb-2 text-[13px] font-medium text-slate-700">Estados de mora</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($statuses as $s)
                                @php $checked = in_array((string) $s->id, array_map('strval', $filters['delinquency_status_ids'])); @endphp
                                <label class="cursor-pointer">
                                    <input type="checkbox" value="{{ $s->id }}" wire:model.live="filters.delinquency_status_ids" class="peer sr-only">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition peer-checked:ring-2 peer-checked:ring-offset-1"
                                          style="border-color: {{ $s->color }}55; color: {{ $s->color }}; {{ $checked ? 'background-color: '.$s->color.'1a; --tw-ring-color: '.$s->color : '' }}">
                                        <span class="size-1.5 rounded-full" style="background-color: {{ $s->color }}"></span>{{ $s->name }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">Sin selección = todos los estados.</p>
                    </div>

                    @if ($groups->isNotEmpty())
                        <div>
                            <p class="mb-2 text-[13px] font-medium text-slate-700">Grupos de clientes</p>
                            <div class="flex flex-wrap gap-3">
                                @foreach ($groups as $g)
                                    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" value="{{ $g->id }}" wire:model.live="filters.group_ids" class="size-4 rounded border-slate-300 text-indigo-600"> {{ $g->name }} <span class="text-xs text-slate-400">({{ $g->customers_count }})</span></label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <x-field label="Saldo desde ($)" error="filters.balance_min"><x-input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="filters.balance_min" /></x-field>
                        <x-field label="Saldo hasta ($)" error="filters.balance_max"><x-input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="filters.balance_max" /></x-field>
                        <x-field label="Días de mora desde"><x-input type="number" min="0" wire:model.live.debounce.500ms="filters.days_min" /></x-field>
                        <x-field label="Días de mora hasta"><x-input type="number" min="0" wire:model.live.debounce.500ms="filters.days_max" /></x-field>
                        <x-field label="Registrado desde"><x-input type="date" wire:model.live="filters.registered_from" /></x-field>
                        <x-field label="Registrado hasta"><x-input type="date" wire:model.live="filters.registered_to" /></x-field>
                        <div class="col-span-2 flex items-end pb-2">
                            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.live="filters.solvent_only" class="size-4 rounded border-slate-300 text-indigo-600"> Solo clientes solventes</label>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-[13px] font-medium text-slate-700">Clientes individuales</p>
                        <div class="relative">
                            <x-input icon="search" type="search" wire:model.live.debounce.300ms="customerSearch" placeholder="Buscar y agregar clientes específicos…" />
                            @if ($customerCandidates->isNotEmpty())
                                <ul class="absolute z-10 mt-1 w-full divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-elevated">
                                    @foreach ($customerCandidates as $c)
                                        <li><button type="button" wire:click="addCustomer({{ $c->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-indigo-50">{{ $c->full_name }} <span class="text-xs text-slate-400">{{ $c->code }}</span></button></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        @if ($selectedCustomers->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($selectedCustomers as $c)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 py-0.5 pr-1 pl-2.5 text-xs text-indigo-700">{{ $c->full_name }}<button wire:click="removeCustomer('{{ $c->id }}')" class="rounded-full p-0.5 hover:bg-indigo-100"><x-icon name="x" class="size-3" /></button></span>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Con clientes individuales seleccionados, la campaña se limita a ellos (combinado con los demás filtros).</p>
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- 3. Plantilla --}}
            <x-card title="3. Mensaje" icon="file-text">
                <div class="space-y-4">
                    @if ($type === 'collection')
                        <x-toggle wire:model.live="use_status_templates" label="Usar la plantilla asociada a cada estado de mora"
                                  description="Cada cliente recibe el mensaje que corresponde a su estado (recordatorio amigable, seguimiento, gestión prioritaria…)." />
                    @endif
                    @if (! ($use_status_templates && $type === 'collection'))
                        <x-field label="Plantilla" error="message_template_id" required>
                            <x-select wire:model.live="message_template_id" placeholder="Selecciona una plantilla…">
                                @foreach ($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                            </x-select>
                        </x-field>
                    @else
                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <table class="w-full text-xs">
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($statuses->where('is_solvent', false) as $s)
                                        <tr><td class="px-3 py-2"><x-status-pill :status="$s" size="xs" /></td><td class="px-3 py-2 text-slate-600">{{ $s->messageTemplate?->name ?? '— sin plantilla (se omitirá)' }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>

        {{-- Resumen y previsualización --}}
        <div class="space-y-6 xl:col-span-2">
            <div class="xl:sticky xl:top-20 space-y-6">
                <div class="rounded-2xl bg-gradient-to-br from-navy-900 to-indigo-800 p-5 text-white shadow-elevated" wire:loading.class="opacity-70">
                    <p class="text-xs font-medium text-indigo-200">Destinatarios que recibirán el mensaje</p>
                    <p class="mt-1 text-4xl font-bold tabular-nums">{{ number_format($summary['eligible']) }}</p>
                    <p class="mt-1 text-xs text-indigo-200">de {{ number_format($summary['matched']) }} clientes que coinciden con los filtros</p>
                    @if ($summary['excluded'])
                        <ul class="mt-4 space-y-1.5 border-t border-white/10 pt-3 text-xs">
                            @foreach ($summary['excluded'] as $reason => $count)
                                <li class="flex justify-between gap-3 text-indigo-100"><span class="flex items-center gap-1.5"><x-icon name="circle-x" class="size-3.5 text-rose-300" /> {{ $reason }}</span><span class="font-semibold tabular-nums">{{ number_format($count) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($isPromotion)
                        <p class="mt-4 rounded-lg bg-white/10 p-2.5 text-[11px] text-indigo-100"><x-icon name="shield-check" class="mr-1 inline size-3.5" />Solo se envía a clientes que autorizaron recibir promociones.</p>
                    @elseif ($type === 'collection')
                        <p class="mt-4 rounded-lg bg-white/10 p-2.5 text-[11px] text-indigo-100"><x-icon name="shield-check" class="mr-1 inline size-3.5" />Los clientes solventes nunca reciben mensajes de cobranza.</p>
                    @endif
                </div>

                <x-card title="Previsualización" :description="'Mensajes reales de '.$previews->count().' destinatarios'" icon="eye" :padding="false">
                    <div class="chat-pattern max-h-[480px] space-y-4 overflow-y-auto p-4 scrollbar-thin">
                        @forelse ($previews as $p)
                            <div>
                                <div class="mb-1 flex items-center gap-2 text-[11px] text-slate-500">
                                    <span class="font-semibold text-slate-700">{{ $p['customer']->full_name }}</span>
                                    <x-status-pill :status="$p['customer']->delinquencyStatus" size="xs" />
                                </div>
                                @if ($p['body'])
                                    <x-wa-bubble :text="$p['body']" :time="now()->format('h:i a')" />
                                @else
                                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">Sin plantilla: este cliente será omitido.</p>
                                @endif
                            </div>
                        @empty
                            <x-empty-state icon="users" title="Sin destinatarios" description="Ajusta los filtros para ver la previsualización." />
                        @endforelse
                    </div>
                </x-card>

                <div class="flex gap-2">
                    <x-button variant="secondary" icon="file-text" class="flex-1" wire:click="save(false)">Guardar borrador</x-button>
                    <x-button icon="send" class="flex-1"
                              x-on:click="$confirm({ title: {{ \Illuminate\Support\Js::from($when === 'later' ? '¿Programar campaña?' : '¿Enviar campaña ahora?') }}, text: {{ \Illuminate\Support\Js::from(number_format($summary['eligible']).' clientes recibirán el mensaje.') }}, confirmText: {{ \Illuminate\Support\Js::from($when === 'later' ? 'Programar' : 'Enviar') }} }, () => $wire.save(true))">
                        {{ $when === 'later' ? 'Programar' : 'Enviar campaña' }}
                    </x-button>
                </div>
            </div>
        </div>
    </div>
</div>
