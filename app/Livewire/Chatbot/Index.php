<?php

namespace App\Livewire\Chatbot;

use App\Models\ChatbotFlow;
use App\Services\ActivityLogger;
use App\Services\Chatbot\IntentDetector;
use App\Services\Settings;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Chatbot')]
class Index extends Component
{
    public array $settings = [];

    public bool $showModal = false;

    #[Locked]
    public ?int $editingId = null;

    public array $form = [];

    public string $testText = '';

    public const SETTING_KEYS = [
        'chatbot.enabled', 'chatbot.verification_method', 'chatbot.verification_ttl_minutes', 'chatbot.max_failed_attempts',
        'chatbot.lock_minutes', 'chatbot.welcome_message', 'chatbot.fallback_message', 'chatbot.ask_dui_message',
        'chatbot.agent_transfer_message', 'chatbot.out_of_hours_message', 'chatbot.returned_to_bot_message',
    ];

    public function mount(): void
    {
        $this->authorize('settings.manage');
        foreach (self::SETTING_KEYS as $key) {
            $this->settings[str_replace('.', '__', $key)] = setting($key);
        }
    }

    public function saveSettings(Settings $store): void
    {
        $this->validate([
            'settings.chatbot__verification_method' => ['required', 'in:phone_match,otp,contract_number'],
            'settings.chatbot__verification_ttl_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'settings.chatbot__max_failed_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'settings.chatbot__lock_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'settings.chatbot__welcome_message' => ['required', 'string', 'max:2000'],
            'settings.chatbot__fallback_message' => ['required', 'string', 'max:1000'],
            'settings.chatbot__ask_dui_message' => ['required', 'string', 'max:1000'],
            'settings.chatbot__agent_transfer_message' => ['required', 'string', 'max:1000'],
            'settings.chatbot__out_of_hours_message' => ['required', 'string', 'max:1000'],
            'settings.chatbot__returned_to_bot_message' => ['required', 'string', 'max:1000'],
        ], attributes: ['settings.chatbot__verification_method' => 'método de verificación']);

        foreach (self::SETTING_KEYS as $key) {
            $store->set($key, $this->settings[str_replace('.', '__', $key)]);
        }
        ActivityLogger::log('settings.chatbot', 'Actualizó la configuración del chatbot');
        $this->dispatch('toast', type: 'success', message: 'Configuración del chatbot guardada');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['intent' => '', 'name' => '', 'keywords' => '', 'response' => '', 'requires_verification' => false, 'priority' => 30, 'is_active' => true];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $flow = ChatbotFlow::findOrFail($id);
        $this->editingId = $flow->id;
        $this->form = $flow->only(['intent', 'name', 'response', 'requires_verification', 'priority', 'is_active']);
        $this->form['keywords'] = implode(', ', $flow->keywords ?? []);
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.intent' => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/', Rule::unique('chatbot_flows', 'intent')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'max:120'],
            'form.keywords' => ['required', 'string', 'max:2000'],
            'form.response' => ['nullable', 'string', 'max:2000'],
            'form.requires_verification' => ['boolean'],
            'form.priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'form.is_active' => ['boolean'],
        ], attributes: ['form.intent' => 'intención', 'form.keywords' => 'palabras clave'])['form'];

        $data['keywords'] = array_values(array_filter(array_map('trim', explode(',', $data['keywords']))));
        $flow = $this->editingId ? ChatbotFlow::findOrFail($this->editingId) : new ChatbotFlow;
        $flow->fill($data)->save();
        ActivityLogger::log('settings.chatbot_flow', "Guardó la intención “{$flow->name}” del chatbot", $flow);
        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Intención guardada');
    }

    public function toggle(int $id): void
    {
        $flow = ChatbotFlow::findOrFail($id);
        $flow->update(['is_active' => ! $flow->is_active]);
    }

    public function render(IntentDetector $detector)
    {
        return view('livewire.chatbot.index', [
            'flows' => ChatbotFlow::orderByDesc('priority')->get(),
            'testResult' => $this->testText !== '' ? ($detector->detect($this->testText)?->name ?? 'Sin coincidencia (respuesta predeterminada)') : null,
            'builtIn' => ['agent', 'balance', 'articles', 'installments', 'next_payment', 'greeting', 'menu', 'logout'],
        ]);
    }
}
