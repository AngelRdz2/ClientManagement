<?php

namespace App\Livewire;

use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mi perfil')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public ?string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ], attributes: ['name' => 'nombre', 'email' => 'correo', 'phone' => 'teléfono']);

        $user->update($data);
        $this->dispatch('toast', type: 'success', message: 'Perfil actualizado');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], attributes: ['current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

        auth()->user()->update(['password' => Hash::make($this->password)]);
        ActivityLogger::log('user.password_changed', 'Cambió su contraseña', auth()->user());
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', type: 'success', message: 'Contraseña actualizada');
    }

    public function render()
    {
        return view('livewire.profile', ['user' => auth()->user()->load('roles.permissions')]);
    }
}
