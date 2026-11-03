<div>
    <x-page-header title="Chatbot" description="Configura las intenciones que reconoce el bot, sus respuestas y la verificación de identidad.">
        <x-slot:actions>
            <x-button variant="secondary" icon="flask-conical" :href="route('simulator')">Probar en el simulador</x-button>
            <x-button icon="plus" wire:click="create">Nueva intención</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div class="space-y-6 xl:col-span-3">
            <x-card title="Probar detección de intención" icon="sparkles">
                <x-input icon="message-circle" wire:model.live.debounce.300ms="testText" placeholder="Escribe una frase, ej. “quisiera saber cuánto debo”" />
                @if ($testResult)
                    <p class="mt-2 text-sm text-slate-600">Intención detectada: <strong class="text-indigo-700">{{ $testResult }}</strong></p>
                @endif
            </x-card>

            <x-table-wrapper>
                <table class="table-base">
                    <thead><tr><th>Prioridad</th><th>Intención</th><th>Palabras clave</th><th>Verificación</th><th>Estado</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($flows as $flow)
                            <tr wire:key="fl-{{ $flow->id }}" @class(['opacity-50' => ! $flow->is_active])>
                                <td class="font-mono text-xs">{{ $flow->priority }}</td>
                                <td><p class="font-medium text-slate-900">{{ $flow->name }}</p><p class="font-mono text-[11px] text-slate-400">{{ $flow->intent }}</p></td>
                                <td class="max-w-sm"><div class="flex flex-wrap gap-1">@foreach (array_slice($flow->keywords ?? [], 0, 8) as $kw)<span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">{{ $kw }}</span>@endforeach @if (count($flow->keywords ?? []) > 8)<span class="text-[11px] text-slate-400">+{{ count($flow->keywords) - 8 }}</span>@endif</div></td>
                                <td>@if ($flow->requires_verification || in_array($flow->intent, ['balance', 'articles', 'installments', 'next_payment']))<x-badge color="indigo" size="xs">DUI + 2.º factor</x-badge>@else<span class="text-xs text-slate-400">No</span>@endif</td>
                                <td><button wire:click="toggle({{ $flow->id }})"><x-badge :color="$flow->is_active ? 'green' : 'slate'">{{ $flow->is_active ? 'Activa' : 'Inactiva' }}</x-badge></button></td>
                                <td class="text-right"><button wire:click="edit({{ $flow->id }})" class="rounded-lg p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600"><x-icon name="pencil" class="size-4" /></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-table-wrapper>
        </div>

        <div class="xl:col-span-2">
            <x-card title="Comportamiento y seguridad" icon="shield-check">
                <form wire:submit="saveSettings" class="space-y-4">
                    <x-toggle wire:model="settings.chatbot__enabled" label="Chatbot activo" description="Si se desactiva, todas las conversaciones esperan a un asesor." />
                    <x-field label="Segundo factor de verificación" error="settings.chatbot__verification_method" hint="Se pide siempre el DUI. Nunca se muestra información financiera sin verificar.">
                        <x-select wire:model="settings.chatbot__verification_method" :options="['phone_match' => 'Número de WhatsApp registrado', 'otp' => 'Código enviado al teléfono registrado', 'contract_number' => 'Últimos 4 dígitos de un contrato']" />
                    </x-field>
                    <div class="grid grid-cols-3 gap-3">
                        <x-field label="Vigencia (min)" error="settings.chatbot__verification_ttl_minutes"><x-input type="number" wire:model="settings.chatbot__verification_ttl_minutes" /></x-field>
                        <x-field label="Intentos" error="settings.chatbot__max_failed_attempts"><x-input type="number" wire:model="settings.chatbot__max_failed_attempts" /></x-field>
                        <x-field label="Bloqueo (min)" error="settings.chatbot__lock_minutes"><x-input type="number" wire:model="settings.chatbot__lock_minutes" /></x-field>
                    </div>
                    @foreach (['welcome_message' => 'Mensaje de bienvenida / menú', 'fallback_message' => 'Respuesta cuando no entiende', 'ask_dui_message' => 'Solicitud de DUI', 'agent_transfer_message' => 'Transferencia a asesor', 'out_of_hours_message' => 'Fuera de horario', 'returned_to_bot_message' => 'Al devolver la conversación al bot'] as $key => $label)
                        <x-field :label="$label" :error="'settings.chatbot__'.$key"><x-textarea wire:model="settings.chatbot__{{ $key }}" rows="{{ $key === 'welcome_message' ? 7 : 2 }}" class="text-[13px]" /></x-field>
                    @endforeach
                    <p class="text-xs text-slate-500">Variables disponibles: @{{empresa}}, @{{horario}}, @{{telefono_empresa}}, @{{correo_empresa}}, @{{direccion_empresa}}.</p>
                    <x-button type="submit" icon="check" class="w-full">Guardar configuración</x-button>
                </form>
            </x-card>
        </div>
    </div>

    <x-modal wire:model="showModal" :title="$editingId ? 'Editar intención' : 'Nueva intención'" icon="bot" maxWidth="xl">
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Nombre" error="form.name" required><x-input wire:model="form.name" /></x-field>
                <x-field label="Identificador" error="form.intent" required hint="minúsculas y guion bajo"><x-input wire:model="form.intent" class="font-mono" :disabled="$editingId && in_array($form['intent'] ?? '', $builtIn)" /></x-field>
            </div>
            <x-field label="Palabras clave (separadas por coma)" error="form.keywords" required hint="Se ignoran mayúsculas y tildes. Un número solo coincide si el mensaje es exactamente ese número.">
                <x-textarea wire:model="form.keywords" rows="3" />
            </x-field>
            @if (! in_array($form['intent'] ?? '', ['agent', 'balance', 'articles', 'installments', 'next_payment', 'greeting', 'menu']))
                <x-field label="Respuesta" error="form.response"><x-textarea wire:model="form.response" rows="4" /></x-field>
            @else
                <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">La respuesta de esta intención la genera el sistema con datos reales (o es una acción, como transferir a un asesor).</p>
            @endif
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Prioridad" error="form.priority"><x-input type="number" wire:model="form.priority" /></x-field>
                <div class="space-y-3 pt-6">
                    <x-toggle wire:model="form.requires_verification" label="Requiere verificación" />
                    <x-toggle wire:model="form.is_active" label="Activa" />
                </div>
            </div>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="check" wire:click="save">Guardar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
