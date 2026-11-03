<div wire:poll.4s>
    <x-page-header title="Simulador de WhatsApp" description="Escribe como si fueras un cliente para probar el chatbot, la verificación de identidad y la transferencia a asesores.">
        <x-slot:actions>
            @if ($conversation)
                <x-button variant="secondary" size="sm" icon="messages-square" :href="route('conversations.index', ['c' => $conversation->id])">Ver en la bandeja</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <x-icon name="flask-conical" class="mt-0.5 size-5 shrink-0" />
        <div><strong>Entorno de simulación.</strong> Esto no es una conexión real con WhatsApp: ningún mensaje sale del sistema. Las respuestas del bot y de los asesores se generan con los datos reales de la base de datos.</div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_420px_1fr]">
        {{-- Configuración --}}
        <div class="space-y-6">
            <x-card title="Escribir como…" icon="smartphone">
                <div class="space-y-3">
                    <div class="relative">
                        <x-input icon="search" type="search" wire:model.live.debounce.300ms="customerSearch" placeholder="Buscar cliente para usar su número…" />
                        @if ($candidates->isNotEmpty())
                            <ul class="absolute z-10 mt-1 w-full divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-elevated">
                                @foreach ($candidates as $c)
                                    <li><button wire:click="useCustomer({{ $c->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-indigo-50">{{ $c->full_name }} <span class="block text-xs text-slate-400">{{ $c->code }} · {{ $c->phone_primary }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <x-field label="Número de WhatsApp (con código de país)" error="phone"><x-input wire:model.live.debounce.500ms="phone" class="font-mono" /></x-field>
                    <x-field label="Nombre de perfil"><x-input wire:model="contactName" /></x-field>
                    <x-button variant="secondary" size="sm" icon="user-x" class="w-full" wire:click="useUnknownNumber">Usar un número no registrado</x-button>
                </div>
                <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs">
                    @if ($identified)
                        <p class="flex items-center gap-1.5 font-medium text-emerald-700"><x-icon name="user-check" class="size-4" /> Número registrado</p>
                        <p class="mt-1 text-slate-600">{{ $identified->full_name }} · {{ $identified->code }}</p>
                        <p class="text-slate-500">DUI para pruebas: <span class="font-mono font-semibold">{{ $identified->dui }}</span></p>
                    @else
                        <p class="flex items-center gap-1.5 font-medium text-slate-600"><x-icon name="user-x" class="size-4" /> Número no registrado: el bot no mostrará información aunque se ingrese un DUI válido (con verificación por número registrado).</p>
                    @endif
                    <p class="mt-2 border-t border-slate-200 pt-2 text-slate-500">Verificación configurada: <strong>{{ ['phone_match' => 'DUI + número registrado', 'otp' => 'DUI + código enviado al teléfono', 'contract_number' => 'DUI + últimos 4 dígitos de contrato'][$verificationMethod] ?? $verificationMethod }}</strong></p>
                </div>
            </x-card>

            <x-card title="Mensajes rápidos" icon="zap">
                <div class="flex flex-wrap gap-2">
                    @foreach (['Hola', 'Quisiera saber cuánto debo', 'Mis artículos', 'Cuotas pendientes', 'Fecha de pago', 'Información', 'Necesito ayuda', 'Quiero hablar con una persona', 'Gracias', 'BAJA'] as $q)
                        <button wire:click="quick(@js($q))" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-indigo-100 hover:text-indigo-700">{{ $q }}</button>
                    @endforeach
                </div>
            </x-card>
        </div>

        {{-- Teléfono simulado --}}
        <div class="mx-auto w-full max-w-[420px]">
            <div class="overflow-hidden rounded-[2.2rem] border-[10px] border-slate-900 bg-slate-900 shadow-2xl">
                <div class="flex items-center gap-3 bg-[#075e54] px-4 py-3 text-white">
                    <span class="grid size-9 place-items-center rounded-full bg-white/20 text-sm font-bold">{{ mb_substr(setting('company.name'), 0, 1) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ setting('company.name') }}</p>
                        <p class="text-[11px] opacity-80">
                            @if (! $conversation) Simulación · sin conversación
                            @else {{ $conversation->status->label() }}@if ($conversation->assignee) · {{ $conversation->assignee->name }}@endif
                            @endif
                        </p>
                    </div>
                    <button wire:click="resetConversation" title="Reiniciar conversación" class="rounded-full p-1.5 hover:bg-white/10"><x-icon name="refresh-cw" class="size-4" /></button>
                </div>
                <div class="chat-pattern h-[520px] space-y-2 overflow-y-auto px-3 py-4 scrollbar-thin" x-data x-init="$el.scrollTop = $el.scrollHeight" wire:key="sim-{{ $entries->last()?->id }}">
                    @if ($realConversation)
                        <p class="rounded-lg bg-rose-50 p-3 text-center text-xs text-rose-700">Este número tiene una conversación real. Usa otro número para simular.</p>
                    @endif
                    @forelse ($entries as $entry)
                        @if ($entry->isEvent())
                            <div class="flex justify-center"><span class="max-w-[90%] rounded-lg bg-white/90 px-2.5 py-1 text-center text-[10.5px] text-slate-500 shadow-sm">{{ $entry->body }}</span></div>
                        @else
                            {{-- Desde la perspectiva del cliente: sus mensajes van a la derecha --}}
                            @php $fromCustomer = $entry->sender_type === \App\Enums\SenderType::Customer; @endphp
                            <x-wa-bubble :text="$entry->body" :outgoing="$fromCustomer" :time="$entry->created_at->format('h:i a')"
                                         :label="! $fromCustomer && $entry->sender_type === \App\Enums\SenderType::Agent ? '👤 '.($entry->user?->name ?? 'Asesor') : null"
                                         :status="$fromCustomer ? 'read' : null" />
                            @if (! $fromCustomer && $entry->message?->status === \App\Enums\MessageStatus::Failed)
                                <p class="text-[10.5px] text-rose-600">⚠ Envío simulado fallido: {{ $entry->message->error_message }}</p>
                            @endif
                        @endif
                    @empty
                        <div class="flex h-full flex-col items-center justify-center text-center text-xs text-slate-500">
                            <x-icon name="message-circle" class="mb-2 size-8 text-slate-400" />
                            Escribe “Hola” para comenzar.
                        </div>
                    @endforelse
                </div>
                <form wire:submit="send" class="flex items-center gap-2 bg-[#f0f2f5] p-2.5">
                    <input type="text" wire:model="text" placeholder="Escribe un mensaje" autocomplete="off" class="flex-1 rounded-full border-0 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500/30 focus:outline-none">
                    <button type="submit" class="grid size-10 place-items-center rounded-full bg-[#00a884] text-white hover:bg-[#029273]" wire:loading.attr="disabled" wire:target="send,quick">
                        <x-icon name="send" class="size-4" wire:loading.remove wire:target="send,quick" />
                        <x-icon name="loader-circle" class="size-4 animate-spin" wire:loading wire:target="send,quick" />
                    </button>
                </form>
            </div>
            @error('text')<p class="mt-2 text-center text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        {{-- Panel lateral --}}
        <div class="space-y-6">
            <x-card title="Estado de la prueba" icon="activity">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Conversación</dt><dd>@if ($conversation)<x-badge :color="$conversation->status->color()">{{ $conversation->status->label() }}</x-badge>@else — @endif</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Identidad</dt><dd>
                        @if ($conversation?->chatbotSession?->isVerified())<x-badge color="green">Verificada</x-badge>
                        @elseif ($conversation?->chatbotSession?->isLocked())<x-badge color="red">Bloqueada</x-badge>
                        @else<x-badge color="slate">Sin verificar</x-badge>@endif
                    </dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Paso del bot</dt><dd class="text-xs">{{ $conversation?->chatbotSession?->state?->label() ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Cliente vinculado</dt><dd class="text-xs">{{ $conversation?->customer?->full_name ?? '—' }}</dd></div>
                </dl>
            </x-card>

            @if ($otpMessages->isNotEmpty())
                <x-card title="Códigos enviados (simulados)" icon="key-round">
                    <p class="mb-2 text-xs text-slate-500">Mensajes de verificación enviados al número registrado del cliente en los últimos 15 minutos.</p>
                    <ul class="space-y-2">
                        @foreach ($otpMessages as $otp)
                            <li class="rounded-lg bg-slate-50 p-2 text-xs"><span class="font-mono text-slate-500">+{{ $otp->phone }}</span><p class="mt-0.5 text-slate-700">{!! wa_format($otp->body) !!}</p></li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @can('settings.manage')
                <x-card title="Simular errores de envío" icon="triangle-alert">
                    <p class="mb-3 text-xs text-slate-500">Porcentaje de mensajes salientes que fallarán aleatoriamente (bot, asesores y campañas). Los números terminados en <span class="font-mono">0000</span> siempre fallan.</p>
                    <div class="flex items-center gap-3">
                        <input type="range" min="0" max="100" step="5" wire:model.live="failureRate" class="flex-1 accent-indigo-600">
                        <span class="w-12 text-right font-semibold tabular-nums">{{ $failureRate }}%</span>
                    </div>
                    <x-button size="sm" variant="secondary" class="mt-3 w-full" wire:click="saveFailureRate">Aplicar</x-button>
                </x-card>
            @endcan
        </div>
    </div>
</div>
