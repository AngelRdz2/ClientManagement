<div>
    <x-page-header title="Usuarios y roles" description="Administra el acceso del equipo. Cada usuario solo ve los módulos que su rol permite.">
        <x-slot:actions>@if ($tab === 'users')<x-button icon="user-plus" wire:click="create">Nuevo usuario</x-button>@endif</x-slot:actions>
    </x-page-header>

    <div class="mb-5 inline-flex rounded-xl bg-slate-200/50 p-1">
        @foreach (['users' => ['Usuarios', 'users'], 'roles' => ['Roles y permisos', 'shield-check']] as $key => [$label, $icon])
            <button wire:click="$set('tab', '{{ $key }}')" @class(['flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium', 'bg-surface text-slate-900 shadow-sm' => $tab === $key, 'text-slate-500' => $tab !== $key])>
                <x-icon :name="$icon" class="size-4" /> {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'users')
        <x-table-wrapper>
            <x-slot:toolbar><x-input icon="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o correo…" class="max-w-sm" /></x-slot:toolbar>
            <table class="table-base">
                <thead><tr><th>Usuario</th><th>Rol</th><th>Cargo</th><th>Último acceso</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $u)
                        <tr wire:key="u-{{ $u->id }}" @class(['opacity-60' => ! $u->is_active])>
                            <td><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-navy-700 text-xs font-bold text-white">{{ $u->initials() }}</span><div><p class="font-medium text-slate-900">{{ $u->name }} @if ($u->id === auth()->id())<span class="text-xs text-slate-400">(tú)</span>@endif</p><p class="text-xs text-slate-500">{{ $u->email }}</p></div></div></td>
                            <td><x-badge color="indigo">{{ $u->roles->first()?->name ?? 'Sin rol' }}</x-badge></td>
                            <td class="text-xs">{{ $u->position ?? '—' }}</td>
                            <td class="text-xs text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'Nunca' }}</td>
                            <td><x-badge :color="$u->is_active ? 'green' : 'slate'" dot>{{ $u->is_active ? 'Activo' : 'Inactivo' }}</x-badge></td>
                            <td><div class="flex justify-end gap-1">
                                <button wire:click="edit({{ $u->id }})" class="rounded-lg p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600"><x-icon name="pencil" class="size-4" /></button>
                                <button x-on:click="$confirm({ title: @js($u->is_active ? '¿Desactivar usuario?' : '¿Activar usuario?'), text: @js($u->name), danger: @js($u->is_active) }, () => $wire.toggleActive({{ $u->id }}))" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><x-icon :name="$u->is_active ? 'user-x' : 'user-check'" class="size-4" /></button>
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <x-slot:footer>{{ $users->links() }}</x-slot:footer>
        </x-table-wrapper>
    @else
        <x-card title="Matriz de permisos" description="Marca lo que cada rol puede hacer. Los cambios aplican de inmediato a todos los usuarios del rol." icon="shield-check" :padding="false">
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <x-input wire:model="newRole" placeholder="Nuevo rol…" class="!py-1.5 text-xs" />
                    <x-button size="sm" variant="secondary" icon="plus" wire:click="createRole">Crear</x-button>
                </div>
            </x-slot:actions>
            @error('newRole')<p class="px-5 pt-3 text-xs text-rose-600">{{ $message }}</p>@enderror
            <div class="overflow-x-auto scrollbar-thin">
                <table class="table-base">
                    <thead><tr><th>Permiso</th>@foreach ($roles as $r)<th class="text-center">{{ $r->name }}<span class="block text-[10px] font-normal normal-case text-slate-400">{{ $r->users_count }} usuarios</span></th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($permissions as $key => $label)
                            <tr>
                                <td><p class="font-medium text-slate-800">{{ $label }}</p><p class="font-mono text-[11px] text-slate-400">{{ $key }}</p></td>
                                @foreach ($roles as $r)
                                    <td class="text-center">
                                        <input type="checkbox" value="{{ $key }}" wire:model="matrix.{{ $r->id }}" class="size-4 rounded border-slate-300 text-indigo-600"
                                               @disabled($r->name === \App\Support\Permissions::ROLE_ADMIN && in_array($key, ['users.manage', 'settings.manage']))>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end border-t border-slate-100 p-4"><x-button icon="check" wire:click="savePermissions">Guardar permisos</x-button></div>
        </x-card>
    @endif

    <x-modal wire:model="showModal" :title="$editingId ? 'Editar usuario' : 'Nuevo usuario'" icon="user-cog" maxWidth="xl">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-field label="Nombre completo" error="form.name" required class="sm:col-span-2"><x-input wire:model="form.name" /></x-field>
            <x-field label="Correo electrónico" error="form.email" required><x-input type="email" wire:model="form.email" /></x-field>
            <x-field label="Teléfono" error="form.phone"><x-input wire:model="form.phone" /></x-field>
            <x-field label="Cargo" error="form.position"><x-input wire:model="form.position" /></x-field>
            <x-field label="Rol" error="form.role" required><x-select wire:model="form.role">@foreach ($roles as $r)<option value="{{ $r->name }}">{{ $r->name }}</option>@endforeach</x-select></x-field>
            <x-field :label="$editingId ? 'Nueva contraseña (opcional)' : 'Contraseña'" error="form.password" :required="! $editingId" hint="Mínimo 8 caracteres con letras y números." class="sm:col-span-2"><x-input type="password" wire:model="form.password" autocomplete="new-password" /></x-field>
            <div class="sm:col-span-2"><x-toggle wire:model="form.is_active" label="Usuario activo" description="Los usuarios inactivos no pueden iniciar sesión." /></div>
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="show = false">Cancelar</x-button>
            <x-button icon="check" wire:click="save">Guardar usuario</x-button>
        </x-slot:footer>
    </x-modal>
</div>
