<div>
    <x-page-header title="Mi perfil" description="Actualiza tus datos de acceso y revisa tus permisos." />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Información personal" icon="user-round">
                <form wire:submit="saveProfile" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Nombre completo" error="name" required class="sm:col-span-2">
                        <x-input wire:model="name" />
                    </x-field>
                    <x-field label="Correo electrónico" error="email" required>
                        <x-input type="email" wire:model="email" />
                    </x-field>
                    <x-field label="Teléfono" error="phone">
                        <x-input wire:model="phone" />
                    </x-field>
                    <div class="sm:col-span-2 flex justify-end">
                        <x-button type="submit" icon="check" wire:target="saveProfile">Guardar cambios</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Cambiar contraseña" icon="key-round">
                <form wire:submit="savePassword" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-field label="Contraseña actual" error="current_password">
                        <x-input type="password" wire:model="current_password" autocomplete="current-password" />
                    </x-field>
                    <x-field label="Nueva contraseña" error="password" hint="Mínimo 8 caracteres, letras y números.">
                        <x-input type="password" wire:model="password" autocomplete="new-password" />
                    </x-field>
                    <x-field label="Confirmar contraseña">
                        <x-input type="password" wire:model="password_confirmation" autocomplete="new-password" />
                    </x-field>
                    <div class="sm:col-span-3 flex justify-end">
                        <x-button type="submit" variant="dark" icon="lock" wire:target="savePassword">Actualizar contraseña</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <x-card title="Rol y permisos" icon="shield-check">
            <div class="flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-navy-700 text-base font-bold text-white">{{ $user->initials() }}</span>
                <div>
                    <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                    <x-badge color="indigo">{{ $user->roleLabel() }}</x-badge>
                </div>
            </div>
            <ul class="mt-5 space-y-2">
                @foreach (\App\Support\Permissions::all() as $permission => $label)
                    <li class="flex items-center gap-2 text-sm">
                        @if ($user->can($permission))
                            <x-icon name="circle-check" class="size-4 text-emerald-500" /> <span class="text-slate-700">{{ $label }}</span>
                        @else
                            <x-icon name="circle-x" class="size-4 text-slate-300" /> <span class="text-slate-400">{{ $label }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="mt-5 text-xs text-slate-500">Último acceso: {{ $user->last_login_at?->format('d/m/Y h:i a') ?? '—' }}</p>
        </x-card>
    </div>
</div>
