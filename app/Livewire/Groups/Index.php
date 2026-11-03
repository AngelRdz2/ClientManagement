<?php

namespace App\Livewire\Groups;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Grupos de clientes')]
class Index extends Component
{
    public bool $showModal = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public ?string $description = '';

    public string $color = '#6366f1';

    // Gestión de miembros
    public bool $showMembers = false;

    #[Locked]
    public ?int $membersGroupId = null;

    public string $memberSearch = '';

    public function mount(): void
    {
        $this->authorize('customers.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'description');
        $this->color = '#6366f1';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $group = CustomerGroup::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->description = $group->description;
        $this->color = $group->color;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('customer_groups', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], attributes: ['name' => 'nombre', 'description' => 'descripción']);

        $group = $this->editingId ? CustomerGroup::findOrFail($this->editingId) : new CustomerGroup;
        $group->fill($data)->save();
        ActivityLogger::log('customer.group_saved', "Guardó el grupo de clientes “{$group->name}”", $group);
        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Grupo guardado');
    }

    public function delete(int $id): void
    {
        $group = CustomerGroup::findOrFail($id);
        $group->customers()->detach();
        $group->delete();
        ActivityLogger::log('customer.group_deleted', "Eliminó el grupo de clientes “{$group->name}”");
        $this->dispatch('toast', type: 'success', message: 'Grupo eliminado');
    }

    public function manageMembers(int $id): void
    {
        $this->membersGroupId = $id;
        $this->memberSearch = '';
        $this->showMembers = true;
    }

    public function addMember(int $customerId): void
    {
        CustomerGroup::findOrFail($this->membersGroupId)->customers()->syncWithoutDetaching([$customerId]);
    }

    public function removeMember(int $customerId): void
    {
        CustomerGroup::findOrFail($this->membersGroupId)->customers()->detach($customerId);
    }

    public function render()
    {
        $group = $this->membersGroupId ? CustomerGroup::find($this->membersGroupId) : null;

        return view('livewire.groups.index', [
            'groups' => CustomerGroup::withCount('customers')->orderBy('name')->get(),
            'membersGroup' => $group,
            'members' => $group ? $group->customers()->orderBy('full_name')->limit(100)->get(['customers.id', 'code', 'full_name']) : collect(),
            'candidates' => $group && mb_strlen($this->memberSearch) >= 2
                ? Customer::search($this->memberSearch)->whereDoesntHave('groups', fn ($q) => $q->whereKey($group->id))->limit(8)->get(['id', 'code', 'full_name'])
                : collect(),
        ]);
    }
}
