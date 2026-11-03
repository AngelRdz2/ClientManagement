<div wire:poll.5s class="-mx-4 -my-6 sm:-mx-6 lg:-mx-8">
    <div class="grid h-[calc(100vh-4rem)] grid-cols-1 overflow-hidden bg-white lg:grid-cols-[340px_1fr] 2xl:grid-cols-[340px_1fr_320px]">

        {{-- ===== Columna 1: lista de conversaciones ===== --}}
        <aside @class(['flex min-h-0 flex-col border-r border-slate-200', 'hidden lg:flex' => $selected])>
            <div class="space-y-3 border-b border-slate-200 p-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-lg font-bold text-slate-900">Conversaciones</h1>
                    @if (app(\App\Services\WhatsApp\WhatsAppManager::class)->isSimulation())
                        <x-badge color="amber" size="xs">Simulación</x-badge>
                    @endif
                </div>
                <x-input icon="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar nombre o número…" />
                <div class="flex flex-wrap gap-1">
                    @foreach (['open' => 'Abiertas', 'pending' => 'Pendientes', 'mine' => 'Mías', 'assigned' => 'Asignadas', 'bot' => 'Bot', 'closed' => 'Cerradas'] as $key => $label)
                        <button wire:click="$set('filter', '{{ $key }}')" @class([
                            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition',
                            'bg-indigo-600 text-white' => $filter === $key,
                            'bg-slate-100 text-slate-600 hover:bg-slate-200' => $filter !== $key,
                        ])>
                            {{ $label }}
                            <span @class(['rounded-full px-1.5 text-[10px]', 'bg-white/20' => $filter === $key, 'bg-rose-500 text-white' => $filter !== $key && $key === 'pending' && $counts->pending > 0, 'bg-white text-slate-500' => $filter !== $key && ! ($key === 'pending' && $counts->pending > 0)])>{{ (int) $counts->{$key} }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <ul class="flex-1 divide-y divide-slate-100 overflow-y-auto scrollbar-thin">
                @forelse ($conversations as $c)
                    <li wire:key="cv-{{ $c->id }}">
                        <button wire:click="select({{ $c->id }})" @class([
                            'flex w-full items-start gap-3 px-4 py-3 text-left transition',
                            'bg-indigo-50' => $selected?->id === $c->id,
                            'hover:bg-slate-50' => $selected?->id !== $c->id,
                        ])>
                            <span @class(['relative grid size-10 shrink-0 place-items-center rounded-full text-sm font-bold', 'bg-gradient-to-br from-indigo-100 to-indigo-200 text-indigo-700' => $c->customer_id, 'bg-slate-200 text-slate-500' => ! $c->customer_id])>
                                {{ mb_substr($c->displayName(), 0, 1) === '+' ? '#' : mb_strtoupper(mb_substr($c->displayName(), 0, 1)) }}
                                @if ($c->status === \App\Enums\ConversationStatus::PendingAgent)
                                    <span class="absolute -top-0.5 -right-0.5 size-3 rounded-full bg-rose-500 ring-2 ring-white"></span>
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold text-slate-900">{{ $c->displayName() }}</span>
                                    <span class="shrink-0 text-[11px] text-slate-400">{{ $c->last_message_at?->isToday() ? $c->last_message_at->format('h:i a') : $c->last_message_at?->format('d/m') }}</span>
                                </span>
                                <span class="mt-0.5 flex items-center justify-between gap-2">
                                    <span class="truncate text-xs text-slate-500">{{ $c->last_message_preview }}</span>
                                    @if ($c->unread_count > 0)
                                        <span class="grid min-w-[18px] shrink-0 place-items-center rounded-full bg-emerald-500 px-1 text-[10px] leading-[18px] font-bold text-white">{{ $c->unread_count }}</span>
                                    @endif
                                </span>
                                <span class="mt-1 flex items-center gap-1.5">
                                    <x-badge :color="$c->status->color()" size="xs">{{ $c->status->label() }}</x-badge>
                                    @if ($c->assignee)<span class="truncate text-[11px] text-slate-400">{{ $c->assignee->name }}</span>@endif
                                </span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li><x-empty-state icon="messages-square" title="Sin conversaciones" description="Las conversaciones de WhatsApp aparecerán aquí." /></li>
                @endforelse
                @if ($conversations->count() >= $limit)
                    <li class="p-3 text-center"><x-button variant="ghost" size="sm" wire:click="loadMore">Cargar más</x-button></li>
                @endif
            </ul>
        </aside>

        {{-- ===== Columna 2: conversación ===== --}}
        <section @class(['flex min-h-0 flex-col', 'hidden lg:flex' => ! $selected])>
            @if ($selected)
                <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <button wire:click="$set('selectedId', null)" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 lg:hidden"><x-icon name="chevron-left" class="size-5" /></button>
                    <span class="grid size-10 place-items-center rounded-full bg-gradient-to-br from-indigo-500 to-navy-700 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($selected->displayName(), 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-900">{{ $selected->displayName() }}</p>
                        <p class="flex items-center gap-2 text-xs text-slate-500">
                            +{{ $selected->phone }} · <x-badge :color="$selected->status->color()" size="xs">{{ $selected->status->label() }}</x-badge>
                            @if ($selected->is_simulated)<x-badge color="amber" size="xs">Simulada</x-badge>@endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        @if ($selected->status !== \App\Enums\ConversationStatus::Closed && $selected->assigned_to !== auth()->id())
                            <x-button size="sm" icon="headset" wire:click="take">Tomar</x-button>
                        @endif
                        @if ($canAssign && $selected->status !== \App\Enums\ConversationStatus::Closed)
                            <div class="flex items-center gap-1">
                                <x-select wire:model="assignTo" class="!py-1.5 text-xs">
                                    <option value="">Asignar a…</option>
                                    @foreach ($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach
                                </x-select>
                                <x-button size="sm" variant="secondary" wire:click="assign">OK</x-button>
                            </div>
                        @endif
                        @if (in_array($selected->status, [\App\Enums\ConversationStatus::PendingAgent, \App\Enums\ConversationStatus::Assigned, \App\Enums\ConversationStatus::InProgress]))
                            <x-button size="sm" variant="soft" icon="bot" x-on:click="$confirm({ title: '¿Devolver al bot?', text: 'El chatbot volverá a responder automáticamente.', confirmText: 'Devolver' }, () => $wire.returnToBot())">Devolver al bot</x-button>
                        @endif
                        @if ($selected->status !== \App\Enums\ConversationStatus::Closed)
                            <x-button size="sm" variant="ghost" icon="circle-check" x-on:click="$confirm({ title: '¿Cerrar conversación?', confirmText: 'Cerrar' }, () => $wire.close())">Cerrar</x-button>
                        @else
                            <x-button size="sm" variant="secondary" icon="refresh-cw" wire:click="reopen">Reabrir</x-button>
                        @endif
                    </div>
                </header>

                <div class="chat-pattern flex-1 space-y-2 overflow-y-auto px-4 py-5 scrollbar-thin sm:px-8"
                     x-data x-init="$el.scrollTop = $el.scrollHeight" x-on:livewire:updated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                     wire:key="thread-{{ $selected->id }}-{{ $entries->last()?->id }}">
                    @foreach ($entries as $entry)
                        @php $prev = $entries[$loop->index - 1] ?? null; @endphp
                        @if (! $prev || ! $prev->created_at->isSameDay($entry->created_at))
                            <div class="my-3 flex justify-center"><span class="rounded-lg bg-white/90 px-3 py-1 text-[11px] font-medium text-slate-500 shadow-sm">{{ $entry->created_at->isToday() ? 'Hoy' : $entry->created_at->translatedFormat('d \d\e F Y') }}</span></div>
                        @endif
                        @if ($entry->isEvent())
                            <div class="flex justify-center"><span class="max-w-md rounded-lg bg-amber-50 px-3 py-1.5 text-center text-[11.5px] text-amber-900 shadow-sm ring-1 ring-amber-200">{{ $entry->body }} · {{ $entry->created_at->format('h:i a') }}</span></div>
                        @elseif ($entry->isNote())
                            <div class="flex justify-end"><div class="max-w-[75%] rounded-xl border border-dashed border-violet-300 bg-violet-50 px-3 py-2 text-[13px] text-violet-900"><p class="mb-0.5 flex items-center gap-1 text-[11px] font-semibold"><x-icon name="lock" class="size-3" /> Nota interna · {{ $entry->user?->name }}</p>{!! wa_format($entry->body) !!}<p class="mt-1 text-right text-[10.5px] text-violet-500">{{ $entry->created_at->format('h:i a') }}</p></div></div>
                        @else
                            @php
                                $outgoing = $entry->sender_type !== \App\Enums\SenderType::Customer;
                                $label = match ($entry->sender_type) {
                                    \App\Enums\SenderType::Bot => '🤖 Bot',
                                    \App\Enums\SenderType::Agent => '👤 '.($entry->user?->name ?? 'Asesor'),
                                    \App\Enums\SenderType::System => '⚙️ Sistema',
                                    default => null,
                                };
                            @endphp
                            <x-wa-bubble :text="$entry->body" :outgoing="$outgoing" :label="$label" :time="$entry->created_at->format('h:i a')" :status="$outgoing ? $entry->message?->status->value : null" />
                            @if ($entry->message?->status === \App\Enums\MessageStatus::Failed)
                                <p class="text-right text-[11px] text-rose-600">No entregado: {{ $entry->message->error_message }}</p>
                            @endif
                        @endif
                    @endforeach
                </div>

                <footer class="border-t border-slate-200 bg-white p-3">
                    @if ($selected->status === \App\Enums\ConversationStatus::Bot)
                        <p class="mb-2 flex items-center gap-1.5 text-xs text-violet-700"><x-icon name="bot" class="size-3.5" /> El bot está atendiendo. Si respondes, tomarás la conversación y el bot dejará de responder.</p>
                    @elseif ($selected->status === \App\Enums\ConversationStatus::PendingAgent)
                        <p class="mb-2 flex items-center gap-1.5 text-xs text-rose-700"><x-icon name="headset" class="size-3.5" /> El cliente espera a un asesor desde {{ $selected->agent_requested_at?->diffForHumans() }}.</p>
                    @endif
                    <form wire:submit="send" class="flex items-end gap-2">
                        <button type="button" wire:click="$toggle('noteMode')" title="Nota interna (no se envía al cliente)"
                                @class(['rounded-xl p-2.5 transition', 'bg-violet-100 text-violet-700' => $noteMode, 'text-slate-400 hover:bg-slate-100' => ! $noteMode])>
                            <x-icon name="lock" class="size-5" />
                        </button>
                        <textarea wire:model="body" rows="1" x-data x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit() }"
                                  @class(['form-input max-h-40 min-h-[44px] flex-1 resize-none', 'border-violet-300 bg-violet-50/50' => $noteMode])
                                  placeholder="{{ $noteMode ? 'Escribe una nota interna…' : 'Escribe un mensaje… (Enter para enviar, Shift+Enter para salto de línea)' }}"></textarea>
                        <button type="submit" class="grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-600 text-white shadow-sm transition hover:bg-indigo-500 disabled:opacity-50" wire:loading.attr="disabled" wire:target="send">
                            <x-icon name="send" class="size-5" />
                        </button>
                    </form>
                    @error('body')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                </footer>
            @else
                <div class="chat-pattern flex flex-1 flex-col items-center justify-center p-8 text-center">
                    <span class="grid size-20 place-items-center rounded-3xl bg-white text-indigo-500 shadow-card"><x-icon name="messages-square" class="size-10" /></span>
                    <h2 class="mt-5 text-lg font-semibold text-slate-900">Selecciona una conversación</h2>
                    <p class="mt-1 max-w-sm text-sm text-slate-500">Atiende a los clientes que solicitaron un asesor, responde mensajes y devuelve conversaciones al bot cuando termines.</p>
                </div>
            @endif
        </section>

        {{-- ===== Columna 3: información del cliente ===== --}}
        @if ($selected)
            <aside class="hidden min-h-0 flex-col overflow-y-auto border-l border-slate-200 bg-slate-50/60 scrollbar-thin 2xl:flex">
                <div class="p-5">
                    @if ($selected->customer)
                        @php $cust = $selected->customer; @endphp
                        <div class="text-center">
                            <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-navy-700 text-xl font-bold text-white">{{ mb_substr($cust->full_name, 0, 1) }}</span>
                            <p class="mt-3 font-semibold text-slate-900">{{ $cust->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $cust->code }} · DUI {{ $cust->dui }}</p>
                            <div class="mt-2 flex justify-center"><x-status-pill :status="$cust->delinquencyStatus" /></div>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-xl bg-white p-3 shadow-card"><p class="text-[11px] text-slate-500">Saldo</p><p class="font-bold text-slate-900">{{ money($cust->outstanding_balance) }}</p></div>
                            <div class="rounded-xl bg-white p-3 shadow-card"><p class="text-[11px] text-slate-500">Días de mora</p><p class="font-bold text-slate-900">{{ $cust->days_overdue }}</p></div>
                        </div>
                        <p class="mt-5 mb-2 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Artículos</p>
                        <ul class="space-y-2">
                            @foreach ($cust->articles->where('balance', '>', 0) as $a)
                                <li class="rounded-xl bg-white p-3 text-xs shadow-card">
                                    <p class="font-medium text-slate-800">{{ $a->description }}</p>
                                    <p class="mt-1 flex justify-between text-slate-500"><span>Saldo {{ money($a->balance) }}</span><span>{{ $a->installments_pending }} cuotas</span></p>
                                </li>
                            @endforeach
                        </ul>
                        @can('customers.view')
                            <x-button variant="secondary" size="sm" icon="user-round" class="mt-4 w-full" :href="route('customers.show', $cust)">Ver ficha completa</x-button>
                        @endcan
                    @else
                        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-4 text-center">
                            <x-icon name="user-x" class="mx-auto size-8 text-slate-400" />
                            <p class="mt-2 text-sm font-medium text-slate-700">Número no identificado</p>
                            <p class="mt-1 text-xs text-slate-500">+{{ $selected->phone }} no coincide con un cliente registrado. Se identificará si el cliente verifica su identidad con el bot.</p>
                        </div>
                    @endif

                    <p class="mt-6 mb-2 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Verificación del bot</p>
                    <div class="rounded-xl bg-white p-3 text-xs shadow-card">
                        @if ($selected->chatbotSession?->isVerified())
                            <p class="flex items-center gap-1.5 text-emerald-700"><x-icon name="shield-check" class="size-4" /> Identidad verificada</p>
                            <p class="mt-1 text-slate-500">Expira {{ $selected->chatbotSession->verification_expires_at->diffForHumans() }}</p>
                        @elseif ($selected->chatbotSession?->isLocked())
                            <p class="flex items-center gap-1.5 text-rose-700"><x-icon name="lock" class="size-4" /> Bloqueada por intentos fallidos</p>
                        @else
                            <p class="flex items-center gap-1.5 text-slate-500"><x-icon name="shield-check" class="size-4" /> Sin verificar</p>
                        @endif
                        @unless (app(\App\Services\WhatsApp\WhatsAppManager::class)->provider($selected->is_simulated ? 'simulator' : null)->isSimulation())
                            <p class="mt-2 border-t border-slate-100 pt-2 {{ $selected->withinServiceWindow() ? 'text-emerald-700' : 'text-amber-700' }}">
                                Ventana de 24 h: {{ $selected->withinServiceWindow() ? 'abierta hasta '.$selected->last_inbound_at->addDay()->format('d/m h:i a') : 'cerrada (solo plantillas aprobadas)' }}
                            </p>
                        @endunless
                    </div>

                    <p class="mt-6 mb-2 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Historial de atención</p>
                    <ul class="space-y-2 text-xs">
                        @forelse ($selected->assignments as $as)
                            <li class="flex gap-2"><span class="mt-1 size-1.5 shrink-0 rounded-full bg-indigo-400"></span><span><span class="font-medium text-slate-700">{{ $as->actionLabel() }}</span>@if ($as->user) · {{ $as->user->name }}@endif<span class="block text-slate-400">{{ $as->created_at->format('d/m h:i a') }}@if ($as->performer && $as->performer->id !== $as->user?->id) · por {{ $as->performer->name }}@endif</span></span></li>
                        @empty
                            <li class="text-slate-400">Atendida solo por el bot.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        @endif
    </div>
</div>
