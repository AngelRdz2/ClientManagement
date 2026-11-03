<?php

namespace App\Livewire\Templates;

use App\Enums\TemplateCategory;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Services\ActivityLogger;
use App\Services\Messaging\TemplateRenderer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Plantillas de mensajes')]
class Index extends Component
{
    #[Url(except: '')]
    public string $category = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showModal = false;

    #[Locked]
    public ?int $editingId = null;

    public array $form = [];

    // Previsualización con cliente de prueba
    public string $previewSearch = '';

    public ?int $previewCustomerId = null;

    public function mount(): void
    {
        $this->authorize('templates.manage');
    }

    public function create(?string $category = null): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'name' => '', 'category' => $category ?: ($this->category ?: TemplateCategory::Collection->value), 'description' => '',
            'body' => 'Hola {{primer_nombre}}, ', 'provider_template_name' => '', 'provider_template_language' => 'es',
            'twilio_content_sid' => '', 'is_active' => true,
        ];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $t = MessageTemplate::findOrFail($id);
        $this->editingId = $t->id;
        $this->form = [
            'name' => $t->name, 'category' => $t->category->value, 'description' => $t->description, 'body' => $t->body,
            'provider_template_name' => $t->provider_template_name, 'provider_template_language' => $t->provider_template_language ?: 'es',
            'twilio_content_sid' => $t->twilio_content_sid, 'is_active' => $t->is_active,
        ];
        $this->showModal = true;
    }

    /** Al pegar el SID desde la consola de Twilio suelen colarse espacios. */
    public function updatedFormTwilioContentSid(?string $value): void
    {
        $this->form['twilio_content_sid'] = trim((string) $value);
    }

    public function duplicate(int $id): void
    {
        $t = MessageTemplate::findOrFail($id);
        // La copia no hereda las aprobaciones: su texto puede cambiar y dejar de coincidir con la plantilla aprobada.
        $copy = $t->replicate(['provider_template_name', 'twilio_content_sid']);
        $copy->name = $t->name.' (copia)';
        $copy->created_by = auth()->id();
        $copy->save();
        $this->dispatch('toast', type: 'success', message: 'Plantilla duplicada');
    }

    public function save(TemplateRenderer $renderer): void
    {
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:150', Rule::unique('message_templates', 'name')->ignore($this->editingId)->whereNull('deleted_at')],
            'form.category' => ['required', Rule::enum(TemplateCategory::class)],
            'form.description' => ['nullable', 'string', 'max:255'],
            'form.body' => ['required', 'string', 'min:10', 'max:4096'],
            'form.provider_template_name' => ['nullable', 'string', 'max:150', 'regex:/^[a-zA-Z0-9_]+$/'],
            'form.provider_template_language' => ['nullable', 'string', 'max:10'],
            'form.twilio_content_sid' => ['nullable', 'string', 'regex:/^HX[0-9a-fA-F]{32}$/'],
            'form.is_active' => ['boolean'],
        ], [
            'form.provider_template_name.regex' => 'El nombre de plantilla de Meta solo admite letras, números y guion bajo.',
            'form.twilio_content_sid.regex' => 'El Content SID de Twilio empieza con HX seguido de 32 caracteres (ej. HX8bd92f91d571d5d819df4b64f2602ec6).',
        ], [
            'form.name' => 'nombre', 'form.category' => 'categoría', 'form.body' => 'contenido',
            'form.provider_template_name' => 'nombre de plantilla en Meta', 'form.twilio_content_sid' => 'Content SID de Twilio',
        ])['form'];

        if ($unknown = $renderer->unknownVariables($data['body'])) {
            $this->addError('form.body', 'Variables no reconocidas: {{'.implode('}}, {{', $unknown).'}}');

            return;
        }

        $template = $this->editingId ? MessageTemplate::findOrFail($this->editingId) : new MessageTemplate(['created_by' => auth()->id()]);
        $template->fill($data + ['updated_by' => auth()->id()]);
        $template->provider_template_name = $data['provider_template_name'] ?: null;
        $template->twilio_content_sid = trim((string) $data['twilio_content_sid']) ?: null;
        $template->save();

        ActivityLogger::log('template.saved', ($this->editingId ? 'Actualizó' : 'Creó')." la plantilla “{$template->name}”", $template);
        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Plantilla guardada');
    }

    public function toggle(int $id): void
    {
        $t = MessageTemplate::findOrFail($id);
        $t->update(['is_active' => ! $t->is_active, 'updated_by' => auth()->id()]);
    }

    public function delete(int $id): void
    {
        $t = MessageTemplate::withCount('delinquencyStatuses')->findOrFail($id);
        if ($t->delinquency_statuses_count > 0) {
            $this->dispatch('alert', type: 'error', title: 'Plantilla en uso', message: 'Está asociada a uno o más estados de mora. Cambia la plantilla de esos estados primero.');

            return;
        }
        $t->delete();
        ActivityLogger::log('template.deleted', "Eliminó la plantilla “{$t->name}”");
        $this->dispatch('toast', type: 'success', message: 'Plantilla eliminada');
    }

    public function insertVariable(string $variable): void
    {
        $this->form['body'] = rtrim($this->form['body'] ?? '').' {{'.$variable.'}}';
    }

    public function selectPreviewCustomer(int $id): void
    {
        $this->previewCustomerId = $id;
        $this->previewSearch = '';
    }

    public function render(TemplateRenderer $renderer)
    {
        $previewCustomer = $this->previewCustomerId
            ? Customer::with(['articles', 'delinquencyStatus'])->find($this->previewCustomerId)
            : Customer::with(['articles', 'delinquencyStatus'])->where('outstanding_balance', '>', 0)->orderBy('id')->first();

        return view('livewire.templates.index', [
            'templates' => MessageTemplate::query()
                ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
                ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('body', 'like', "%{$this->search}%")))
                ->withCount(['delinquencyStatuses', 'campaigns'])
                ->with('delinquencyStatuses:id,name,color,message_template_id')
                ->orderBy('category')->orderBy('name')
                ->get(),
            'counts' => MessageTemplate::selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category'),
            'variables' => TemplateRenderer::VARIABLES,
            'previewCustomer' => $previewCustomer,
            'preview' => $this->showModal && ($this->form['body'] ?? '') !== '' ? $renderer->render($this->form['body'], $previewCustomer) : null,
            'previewCandidates' => mb_strlen($this->previewSearch) >= 2 ? Customer::search($this->previewSearch)->limit(6)->get(['id', 'code', 'full_name']) : collect(),
            'renderer' => $renderer,
        ]);
    }
}
