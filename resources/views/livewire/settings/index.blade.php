<div>
    <x-page-header title="Configuración" description="Parámetros generales del sistema, integración con WhatsApp y reglas de comunicación." />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[260px_1fr]">
        <nav class="space-y-1 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-card xl:self-start">
            @foreach ($tabs as $key => [$label, $icon])
                <button wire:click="$set('tab', '{{ $key }}')" @class(['flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium', 'bg-indigo-50 text-indigo-700' => $tab === $key, 'text-slate-600 hover:bg-slate-50' => $tab !== $key])>
                    <x-icon :name="$icon" class="size-4" /> {{ $label }}
                </button>
            @endforeach
            <div class="my-2 border-t border-slate-100"></div>
            <a href="{{ route('chatbot.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"><x-icon name="bot" class="size-4" /> Chatbot y mensajes</a>
            <a href="{{ route('delinquency.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"><x-icon name="gauge" class="size-4" /> Estados de mora</a>
            <a href="{{ route('templates.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"><x-icon name="file-text" class="size-4" /> Plantillas</a>
        </nav>

        <form wire:submit="save" class="min-w-0 space-y-6">
            @if ($tab === 'company')
                <x-card title="Información de la empresa" description="Se usa en las variables de empresa de las plantillas y en las respuestas del chatbot." icon="building-2">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="Nombre comercial" error="values.company__name" required class="sm:col-span-2"><x-input wire:model="values.company__name" /></x-field>
                        <x-field label="Teléfono" error="values.company__phone"><x-input wire:model="values.company__phone" /></x-field>
                        <x-field label="Correo" error="values.company__email"><x-input type="email" wire:model="values.company__email" /></x-field>
                        <x-field label="Sitio web" error="values.company__website" class="sm:col-span-2"><x-input wire:model="values.company__website" placeholder="https://" /></x-field>
                        <x-field label="Dirección" error="values.company__address" class="sm:col-span-2"><x-textarea wire:model="values.company__address" rows="2" /></x-field>
                    </div>
                </x-card>
            @endif

            @if ($tab === 'whatsapp')
                <x-card title="Proveedor activo" description="Cambia de proveedor sin modificar el resto del sistema." icon="smartphone">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        @foreach ($providers as $name => $provider)
                            <label @class(['relative cursor-pointer rounded-xl border-2 p-4 transition', 'border-indigo-500 bg-indigo-50/50' => $values['whatsapp__provider'] === $name, 'border-slate-200 hover:border-slate-300' => $values['whatsapp__provider'] !== $name])>
                                <input type="radio" wire:model.live="values.whatsapp__provider" value="{{ $name }}" class="sr-only">
                                <p class="font-semibold text-slate-900">{{ $provider->label() }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ ['simulator' => 'Pruebas sin costo. No envía mensajes reales.', 'meta' => 'API oficial de Meta (Graph API).', 'twilio' => 'WhatsApp a través de Twilio.'][$name] }}</p>
                                <div class="mt-3">
                                    @if ($provider->isSimulation())<x-badge color="amber" size="xs">Simulación</x-badge>
                                    @elseif ($provider->isConfigured())<x-badge color="green" size="xs">Credenciales configuradas</x-badge>
                                    @else<x-badge color="red" size="xs">Faltan credenciales</x-badge>@endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @if ($values['whatsapp__provider'] !== 'simulator' && ! $providers[$values['whatsapp__provider']]->isConfigured())
                        <p class="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700">El proveedor seleccionado no tiene credenciales completas: los envíos fallarán hasta configurarlas.</p>
                    @endif
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="Límite de envíos por minuto" error="values.whatsapp__rate_limit_per_minute" hint="Respeta los límites del proveedor y evita bloqueos."><x-input type="number" min="1" wire:model="values.whatsapp__rate_limit_per_minute" /></x-field>
                        <x-field label="Errores simulados (%)" error="values.whatsapp__simulator_failure_rate" hint="Solo aplica al simulador."><x-input type="number" min="0" max="100" wire:model="values.whatsapp__simulator_failure_rate" /></x-field>
                    </div>
                </x-card>

                <x-card title="WhatsApp Cloud API (Meta)" icon="key-round">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="Phone Number ID" hint="{{ in_array('whatsapp.meta_phone_number_id', $envKeys) ? 'Definido en .env (WHATSAPP_META_PHONE_NUMBER_ID)' : '' }}"><x-input wire:model="values.whatsapp__meta_phone_number_id" class="font-mono" :disabled="in_array('whatsapp.meta_phone_number_id', $envKeys)" /></x-field>
                        <x-field label="Business Account ID"><x-input wire:model="values.whatsapp__meta_business_account_id" class="font-mono" :disabled="in_array('whatsapp.meta_business_account_id', $envKeys)" /></x-field>
                        @foreach (['whatsapp.meta_access_token' => 'Access token', 'whatsapp.meta_app_secret' => 'App secret (firma de webhooks)', 'whatsapp.meta_verify_token' => 'Verify token (verificación del webhook)'] as $key => $label)
                            @include('livewire.settings.partials.secret', ['key' => $key, 'label' => $label])
                        @endforeach
                    </div>
                    <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                        <p class="font-semibold text-slate-700">URL del webhook (configúrala en Meta for Developers)</p>
                        <code class="mt-1 block break-all text-indigo-700">{{ $webhooks['meta'] }}</code>
                        <p class="mt-1">Suscribe los campos <code>messages</code>. Las firmas se validan con el App secret (X-Hub-Signature-256).</p>
                    </div>
                </x-card>

                <x-card title="Twilio WhatsApp" icon="key-round">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="Account SID"><x-input wire:model="values.whatsapp__twilio_account_sid" class="font-mono" :disabled="in_array('whatsapp.twilio_account_sid', $envKeys)" /></x-field>
                        <x-field label="Número WhatsApp remitente" hint="Formato internacional, ej. +14155238886"><x-input wire:model="values.whatsapp__twilio_from_number" class="font-mono" :disabled="in_array('whatsapp.twilio_from_number', $envKeys)" /></x-field>
                        @include('livewire.settings.partials.secret', ['key' => 'whatsapp.twilio_auth_token', 'label' => 'Auth token'])
                    </div>
                    <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                        <p class="font-semibold text-slate-700">URL del webhook (“When a message comes in”)</p>
                        <code class="mt-1 block break-all text-indigo-700">{{ $webhooks['twilio'] }}</code>
                    </div>
                </x-card>

                <x-card title="Probar conexión" icon="send">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <x-field label="Número de prueba" error="testNumber" class="flex-1"><x-input wire:model="testNumber" placeholder="7000-0000" /></x-field>
                        <x-button type="button" variant="secondary" icon="send" wire:click="sendTest">Enviar mensaje de prueba</x-button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Usa el proveedor guardado actualmente. Con Meta o Twilio el mensaje es real.</p>
                </x-card>
            @endif

            @if ($tab === 'hours')
                <x-card title="Horario de atención" description="Fuera de horario, el bot informa al cliente que un asesor lo contactará después." icon="clock">
                    <div class="space-y-2">
                        @foreach (\App\Livewire\Settings\Index::DAYS as $day => $label)
                            <div class="grid grid-cols-[110px_80px_1fr] items-center gap-3 rounded-xl px-3 py-2 odd:bg-slate-50 sm:grid-cols-[140px_90px_1fr]">
                                <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                                <x-toggle wire:model.live="schedule.{{ $day }}.open" />
                                @if ($schedule[$day]['open'])
                                    <div class="flex items-center gap-2"><x-input type="time" wire:model="schedule.{{ $day }}.from" class="max-w-[130px]" /> <span class="text-slate-400">a</span> <x-input type="time" wire:model="schedule.{{ $day }}.to" class="max-w-[130px]" /></div>
                                @else
                                    <span class="text-sm text-slate-400">Cerrado</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-4 text-xs text-slate-500">Horario actual: {{ \App\Services\Messaging\BusinessHours::describe() }} · Zona horaria: {{ $values['hours__timezone'] }}</p>
                </x-card>
            @endif

            @if ($tab === 'notifications')
                <x-card title="Notificaciones internas" description="Elige qué eventos generan alertas en el encabezado y el centro de notificaciones." icon="bell">
                    <div class="space-y-4">
                        <x-toggle wire:model="values.notifications__agent_requested" label="Cliente solicita un asesor" description="A todos los usuarios que atienden conversaciones." />
                        <x-toggle wire:model="values.notifications__new_conversation" label="Nueva conversación recibida" />
                        <x-toggle wire:model="values.notifications__conversation_assigned" label="Nueva asignación de conversación" description="Al asesor asignado." />
                        <x-toggle wire:model="values.notifications__campaign_finished" label="Campaña finalizada" description="Al creador de la campaña." />
                        <x-toggle wire:model="values.notifications__messages_failed" label="Mensajes fallidos en campañas" />
                        <x-toggle wire:model="values.notifications__import_failed" label="Error en una importación" />
                    </div>
                </x-card>
            @endif

            @if ($tab === 'communication')
                <x-card title="Preferencias de comunicación" icon="message-circle">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="Palabras para darse de baja" error="values.communication__opt_out_keywords" hint="Separadas por coma. Si el cliente escribe exactamente una, deja de recibir mensajes masivos." class="sm:col-span-2"><x-input wire:model="values.communication__opt_out_keywords" /></x-field>
                        <x-field label="Respuesta al darse de baja" class="sm:col-span-2"><x-textarea wire:model="values.communication__opt_out_message" rows="2" /></x-field>
                        <x-field label="Enviar cobranza desde" error="values.communication__collection_send_from"><x-input type="time" wire:model="values.communication__collection_send_from" /></x-field>
                        <x-field label="Enviar cobranza hasta" error="values.communication__collection_send_until"><x-input type="time" wire:model="values.communication__collection_send_until" /></x-field>
                        <x-field label="Horas mínimas entre mensajes de cobranza al mismo cliente" error="values.communication__min_hours_between_collection_messages" hint="Evita mensajes duplicados entre campañas. 0 = sin restricción." class="sm:col-span-2"><x-input type="number" min="0" wire:model="values.communication__min_hours_between_collection_messages" /></x-field>
                    </div>
                </x-card>
            @endif

            @if ($tab === 'collection')
                <x-card title="Cobranza" icon="hand-coins">
                    <x-toggle wire:model="values.collection__auto_recalculate_enabled" label="Recálculo automático de estados de mora"
                              description="Cada día a la 01:00 (y al registrar pagos) se recalculan días y estado de mora según la próxima fecha de pago de cada artículo. Actívalo solo si las fechas de pago son confiables; los estados manuales se respetan." />
                    <p class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">Requiere el programador de tareas activo: <code>php artisan schedule:work</code> (desarrollo) o un cron con <code>schedule:run</code> (producción).</p>
                </x-card>
            @endif

            <div class="flex justify-end"><x-button type="submit" icon="check">Guardar cambios</x-button></div>
        </form>
    </div>
</div>
