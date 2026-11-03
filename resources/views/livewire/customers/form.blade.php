<div>
    <x-page-header :title="$isNew ? 'Nuevo cliente' : 'Editar cliente'"
                   :description="$isNew ? 'Registra los datos generales del cliente. Los artículos se agregan después desde su ficha.' : $full_name.' · '.$code"
                   :back="$isNew ? route('customers.index') : route('customers.show', $customerId)" />

    <form wire:submit="save" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Datos generales" description="Identificación y contacto" icon="user-round">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                    <x-field label="Código de cliente" error="code" required class="sm:col-span-2">
                        <x-input wire:model="code" class="font-mono" />
                    </x-field>
                    <x-field label="Nombre completo" error="full_name" required class="sm:col-span-4">
                        <x-input wire:model="full_name" placeholder="Nombres y apellidos" />
                    </x-field>
                    <x-field label="DUI" error="dui" required class="sm:col-span-2" hint="Formato 00000000-0">
                        <x-input wire:model.blur="dui" class="font-mono" placeholder="00000000-0" maxlength="10" />
                    </x-field>
                    <x-field label="Teléfono principal (WhatsApp)" error="phone_primary" required class="sm:col-span-2">
                        <x-input wire:model="phone_primary" icon="phone" placeholder="7000-0000" />
                    </x-field>
                    <x-field label="Teléfono secundario" error="phone_secondary" class="sm:col-span-2">
                        <x-input wire:model="phone_secondary" icon="phone" placeholder="Opcional" />
                    </x-field>
                    <x-field label="Correo electrónico" error="email" class="sm:col-span-3">
                        <x-input type="email" wire:model="email" icon="mail" placeholder="Opcional" />
                    </x-field>
                    <x-field label="Fecha de registro" error="registered_at" required class="sm:col-span-3">
                        <x-input type="date" wire:model="registered_at" />
                    </x-field>
                    <x-field label="Dirección" error="address" class="sm:col-span-6">
                        <x-textarea wire:model="address" rows="2" placeholder="Colonia, calle, número, municipio" />
                    </x-field>
                    <x-field label="Observaciones" error="notes" class="sm:col-span-6">
                        <x-textarea wire:model="notes" rows="3" placeholder="Notas internas sobre el cliente" />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Estado" icon="gauge">
                <div class="space-y-4">
                    <x-field label="Estado del cliente" error="status">
                        <x-select wire:model="status" :options="\App\Enums\CustomerStatus::options()" />
                    </x-field>
                    @if ($isNew)
                        <x-field label="Estado de cobranza inicial" error="delinquency_status_id" required>
                            <x-select wire:model="delinquency_status_id" placeholder="Selecciona…">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                        <x-field label="Días de mora" error="days_overdue">
                            <x-input type="number" min="0" wire:model="days_overdue" />
                        </x-field>
                    @else
                        <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                            <x-icon name="info" class="mb-1 size-4 text-indigo-500" />
                            El estado de mora se modifica desde la ficha del cliente con <strong>“Cambiar estado”</strong>, para registrar el motivo en el historial.
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card title="Preferencias de comunicación" icon="message-circle">
                <div class="space-y-4">
                    <x-toggle wire:model="accepts_collection_messages" label="Mensajes de cobranza" description="Recordatorios de pago por WhatsApp. Nunca se envían a clientes solventes." />
                    <x-toggle wire:model="accepts_promotions" label="Acepta promociones" description="Consentimiento para recibir ofertas comerciales. Se registra la fecha." />
                </div>
            </x-card>

            @if ($groups->isNotEmpty())
                <x-card title="Grupos" icon="layers">
                    <div class="space-y-2">
                        @foreach ($groups as $group)
                            <label class="flex items-center gap-2.5 text-sm text-slate-700">
                                <input type="checkbox" value="{{ $group->id }}" wire:model="group_ids" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="size-2.5 rounded-full" style="background-color: {{ $group->color }}"></span>
                                {{ $group->name }}
                            </label>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <div class="flex gap-2">
                <x-button variant="secondary" class="flex-1" :href="$isNew ? route('customers.index') : route('customers.show', $customerId)">Cancelar</x-button>
                <x-button type="submit" icon="check" class="flex-1" wire:target="save">{{ $isNew ? 'Registrar cliente' : 'Guardar cambios' }}</x-button>
            </div>
        </div>
    </form>
</div>
