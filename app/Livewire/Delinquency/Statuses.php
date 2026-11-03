<?php

namespace App\Livewire\Delinquency;

use App\Enums\TemplateCategory;
use App\Models\DelinquencyStatus;
use App\Models\MessageTemplate;
use App\Services\ActivityLogger;
use App\Services\DelinquencyService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Estados de mora')]
class Statuses extends Component
{
    public bool $showModal = false;

    #[Locked]
    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        $this->authorize('delinquency.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'name' => '', 'code' => '', 'description' => '', 'min_days_overdue' => 0, 'color' => '#6366f1',
            'priority' => (int) DelinquencyStatus::max('priority') + 1, 'message_template_id' => '',
            'allows_automatic_messages' => true, 'exclude_from_campaigns' => false, 'is_solvent' => false,
            'is_critical' => false, 'is_auto_assignable' => true, 'is_active' => true,
        ];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $status = DelinquencyStatus::findOrFail($id);
        $this->editingId = $status->id;
        $this->form = $status->only([
            'name', 'code', 'description', 'min_days_overdue', 'color', 'priority', 'allows_automatic_messages',
            'exclude_from_campaigns', 'is_solvent', 'is_critical', 'is_auto_assignable', 'is_active',
        ]);
        $this->form['message_template_id'] = (string) $status->message_template_id;
        $this->showModal = true;
    }

    public function updatedFormName(string $value): void
    {
        if (! $this->editingId) {
            $this->form['code'] = Str::upper(Str::slug($value, '_'));
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:100', Rule::unique('delinquency_statuses', 'name')->ignore($this->editingId)],
            'form.code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', Rule::unique('delinquency_statuses', 'code')->ignore($this->editingId)],
            'form.description' => ['nullable', 'string', 'max:255'],
            'form.min_days_overdue' => ['required', 'integer', 'min:0', 'max:5000'],
            'form.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.priority' => ['required', 'integer', 'min:0', 'max:999'],
            'form.message_template_id' => ['nullable', 'exists:message_templates,id'],
            'form.allows_automatic_messages' => ['boolean'],
            'form.exclude_from_campaigns' => ['boolean'],
            'form.is_solvent' => ['boolean'],
            'form.is_critical' => ['boolean'],
            'form.is_auto_assignable' => ['boolean'],
            'form.is_active' => ['boolean'],
        ], [
            'form.code.regex' => 'El código solo admite mayúsculas, números y guion bajo.',
        ], [
            'form.name' => 'nombre', 'form.code' => 'código', 'form.min_days_overdue' => 'días de mora', 'form.color' => 'color',
            'form.priority' => 'prioridad', 'form.message_template_id' => 'plantilla',
        ])['form'];

        $data['message_template_id'] = $data['message_template_id'] ?: null;
        // Un estado solvente nunca envía mensajes de cobranza.
        if ($data['is_solvent']) {
            $data['allows_automatic_messages'] = false;
            $data['message_template_id'] = null;
        }

        if ($this->editingId && ! $data['is_active'] && DelinquencyStatus::find($this->editingId)?->customers()->exists()) {
            $this->dispatch('alert', type: 'warning', title: 'Estado con clientes', message: 'El estado quedó inactivo pero mantiene clientes asignados. No estará disponible para nuevas asignaciones.');
        }

        $status = $this->editingId ? DelinquencyStatus::findOrFail($this->editingId) : new DelinquencyStatus;
        $status->fill($data)->save();
        app(DelinquencyService::class)->flushCache();

        ActivityLogger::log('status.config_saved', ($this->editingId ? 'Actualizó' : 'Creó')." el estado de mora “{$status->name}”", $status);
        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Estado de mora guardado');
    }

    public function delete(int $id): void
    {
        $status = DelinquencyStatus::withCount('customers')->findOrFail($id);
        if ($status->customers_count > 0) {
            $this->dispatch('alert', type: 'error', title: 'No se puede eliminar', message: "Hay {$status->customers_count} clientes con este estado. Desactívalo o reasigna a los clientes primero.");

            return;
        }

        $status->delete();
        ActivityLogger::log('status.config_deleted', "Eliminó el estado de mora “{$status->name}”");
        $this->dispatch('toast', type: 'success', message: 'Estado eliminado');
    }

    public function render()
    {
        return view('livewire.delinquency.statuses', [
            'statuses' => DelinquencyStatus::query()
                ->ordered()
                ->with('messageTemplate:id,name')
                ->withCount('customers')
                ->withSum('customers', 'outstanding_balance')
                ->get(),
            'templates' => MessageTemplate::query()->active()
                ->whereIn('category', [TemplateCategory::Collection->value, TemplateCategory::Reminder->value])
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
