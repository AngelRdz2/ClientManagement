<?php

namespace App\Livewire\Users;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Title('Usuarios y roles')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: 'users')]
    public string $tab = 'users';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showModal = false;

    #[Locked]
    public ?int $editingId = null;

    public array $form = [];

    /** @var array<string, list<string>> id de rol => permisos */
    public array $matrix = [];

    public string $newRole = '';

    public function mount(): void
    {
        $this->authorize('users.manage');
        $this->loadMatrix();
    }

    protected function loadMatrix(): void
    {
        $this->matrix = Role::with('permissions:id,name')->orderBy('id')->get()
            ->mapWithKeys(fn (Role $r) => [(string) $r->id => $r->permissions->pluck('name')->all()])
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name' => '', 'email' => '', 'phone' => '', 'position' => '', 'role' => Permissions::ROLE_AGENT, 'is_active' => true, 'password' => ''];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $user = User::with('roles')->findOrFail($id);
        $this->editingId = $user->id;
        $this->form = [
            'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'position' => $user->position,
            'role' => $user->roles->first()?->name ?? '', 'is_active' => $user->is_active, 'password' => '',
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:120'],
            'form.email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.position' => ['nullable', 'string', 'max:100'],
            'form.role' => ['required', 'exists:roles,name'],
            'form.is_active' => ['boolean'],
            'form.password' => [$this->editingId ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
        ], attributes: ['form.name' => 'nombre', 'form.email' => 'correo', 'form.role' => 'rol', 'form.password' => 'contraseña'])['form'];

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;

        if ($this->editingId === auth()->id() && (! $data['is_active'] || $data['role'] !== Permissions::ROLE_ADMIN && $user->hasRole(Permissions::ROLE_ADMIN))) {
            $this->addError('form.role', 'No puedes desactivarte ni quitarte el rol de administrador a ti mismo.');

            return;
        }
        if ($user->exists && $user->hasRole(Permissions::ROLE_ADMIN) && $data['role'] !== Permissions::ROLE_ADMIN && $this->adminCount() <= 1) {
            $this->addError('form.role', 'Debe existir al menos un administrador activo.');

            return;
        }

        $user->fill(collect($data)->only(['name', 'email', 'phone', 'position', 'is_active'])->all());
        if (filled($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->email_verified_at ??= now();
        $user->save();
        $user->syncRoles([$data['role']]);

        ActivityLogger::log('user.saved', ($this->editingId ? 'Actualizó' : 'Creó')." al usuario {$user->name} ({$data['role']})", $user);
        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Usuario guardado');
    }

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            $this->dispatch('alert', type: 'warning', title: 'Acción no permitida', message: 'No puedes desactivar tu propio usuario.');

            return;
        }
        if ($user->is_active && $user->hasRole(Permissions::ROLE_ADMIN) && $this->adminCount() <= 1) {
            $this->dispatch('alert', type: 'warning', title: 'Acción no permitida', message: 'Debe existir al menos un administrador activo.');

            return;
        }
        $user->update(['is_active' => ! $user->is_active]);
        ActivityLogger::log('user.'.($user->is_active ? 'activated' : 'deactivated'), ($user->is_active ? 'Activó' : 'Desactivó')." al usuario {$user->name}", $user);
        $this->dispatch('toast', type: 'success', message: $user->is_active ? 'Usuario activado' : 'Usuario desactivado');
    }

    public function savePermissions(): void
    {
        foreach ($this->matrix as $roleId => $permissions) {
            $role = Role::findById((int) $roleId, 'web');
            $roleName = $role->name;
            $permissions = array_values(array_intersect((array) $permissions, array_keys(Permissions::all())));
            // El administrador conserva siempre la administración de usuarios para no perder el acceso.
            if ($roleName === Permissions::ROLE_ADMIN) {
                $permissions = array_values(array_unique([...$permissions, Permissions::USERS_MANAGE, Permissions::SETTINGS_MANAGE]));
            }
            $role->syncPermissions($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->loadMatrix();
        ActivityLogger::log('user.permissions', 'Actualizó la matriz de permisos por rol');
        $this->dispatch('toast', type: 'success', message: 'Permisos actualizados');
    }

    public function createRole(): void
    {
        $this->validate(['newRole' => ['required', 'string', 'max:60', 'unique:roles,name']], attributes: ['newRole' => 'nombre del rol']);
        Role::create(['name' => trim($this->newRole), 'guard_name' => 'web'])->givePermissionTo(Permissions::CUSTOMERS_VIEW);
        ActivityLogger::log('user.role_created', "Creó el rol “{$this->newRole}”");
        $this->reset('newRole');
        $this->loadMatrix();
        $this->dispatch('toast', type: 'success', message: 'Rol creado');
    }

    private function adminCount(): int
    {
        return User::role(Permissions::ROLE_ADMIN)->where('is_active', true)->count();
    }

    public function render()
    {
        foreach (array_keys(Permissions::all()) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        return view('livewire.users.index', [
            'users' => User::query()->with('roles:id,name')
                ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
                ->orderByDesc('is_active')->orderBy('name')->paginate(15),
            'roles' => Role::withCount('users')->orderBy('id')->get(),
            'permissions' => Permissions::all(),
        ]);
    }
}
