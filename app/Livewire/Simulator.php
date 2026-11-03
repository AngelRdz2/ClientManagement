<?php

namespace App\Livewire;

use App\Enums\MessageDirection;
use App\Models\ChatbotSession;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\ActivityLogger;
use App\Services\Conversations\InboundMessageService;
use App\Services\Settings;
use App\Services\WhatsApp\Data\InboundMessage;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Simulador de WhatsApp: permite escribir como si fuera un cliente y comprobar la respuesta del bot y de los asesores.
 * NO es una conexión real con WhatsApp: los mensajes se procesan con el proveedor de simulación.
 */
#[Title('Simulador de WhatsApp')]
class Simulator extends Component
{
    #[Url(as: 'tel', except: '')]
    public string $phone = '';

    public string $contactName = '';

    public string $text = '';

    public string $customerSearch = '';

    public int $failureRate = 0;

    public function mount(): void
    {
        $this->authorize('conversations.attend');
        $this->failureRate = (int) setting('whatsapp.simulator_failure_rate', 0);

        if ($this->phone === '') {
            $demo = Customer::where('dui', '01234567-8')->first() ?? Customer::active()->first();
            $this->usePhone($demo?->phone_primary ?? '7000-0001', $demo?->full_name ?? 'Cliente de prueba');
        }
    }

    public function usePhone(string $phone, string $name = ''): void
    {
        $this->phone = (string) Phone::normalize($phone);
        $this->contactName = $name;
        $this->customerSearch = '';
    }

    public function useCustomer(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->usePhone($customer->phone_primary, $customer->full_name);
    }

    public function useUnknownNumber(): void
    {
        $this->usePhone('7'.random_int(1000000, 9999999), 'Número desconocido');
    }

    public function send(InboundMessageService $inbound): void
    {
        $this->validate([
            'phone' => ['required', 'regex:/^\d{8,15}$/'],
            'text' => ['required', 'string', 'max:1000'],
        ], attributes: ['phone' => 'número', 'text' => 'mensaje']);

        // Nunca inyectar mensajes simulados en una conversación real (las respuestas saldrían por el proveedor real).
        if (Conversation::where('phone', $this->phone)->where('is_simulated', false)->exists()) {
            $this->addError('text', 'Este número tiene una conversación real de WhatsApp. Usa otro número para simular.');

            return;
        }

        $inbound->receive(new InboundMessage(
            from: $this->phone,
            body: trim($this->text),
            providerMessageId: 'sim_in_'.Str::lower((string) Str::ulid()),
            contactName: $this->contactName ?: null,
            receivedAt: CarbonImmutable::now(),
        ), 'simulator', simulated: true);

        $this->reset('text');
    }

    public function quick(string $text, InboundMessageService $inbound): void
    {
        $this->text = $text;
        $this->send($inbound);
    }

    public function resetConversation(): void
    {
        $conversation = Conversation::where('phone', $this->phone)->where('is_simulated', true)->first();
        if ($conversation) {
            ChatbotSession::where('conversation_id', $conversation->id)->delete();
            $conversation->entries()->delete();
            $conversation->delete();
            ActivityLogger::log('simulator.reset', 'Reinició la conversación simulada de +'.$this->phone);
        }
        $this->dispatch('toast', type: 'success', message: 'Conversación simulada reiniciada');
    }

    public function saveFailureRate(Settings $settings): void
    {
        $this->authorize('settings.manage');
        $this->validate(['failureRate' => ['required', 'integer', 'min:0', 'max:100']]);
        $settings->set('whatsapp.simulator_failure_rate', $this->failureRate);
        $this->dispatch('toast', type: 'success', message: "Tasa de error simulada: {$this->failureRate}%");
    }

    public function render()
    {
        $conversation = $this->phone ? Conversation::with(['customer:id,full_name,code', 'assignee:id,name', 'chatbotSession'])->where('phone', $this->phone)->first() : null;

        return view('livewire.simulator', [
            'conversation' => $conversation,
            'entries' => $conversation ? $conversation->entries()->with(['message:id,status,error_message', 'user:id,name'])->where('type', '!=', 'note')->latest('id')->limit(120)->get()->reverse()->values() : collect(),
            'realConversation' => $conversation && ! $conversation->is_simulated,
            // Códigos de verificación enviados al número registrado (para pruebas con el método OTP)
            'otpMessages' => Message::query()->where('direction', MessageDirection::Outbound)->where('provider', 'simulator')
                ->where('meta->otp', true)->where('created_at', '>=', now()->subMinutes(15))->latest('id')->limit(3)->get(['id', 'phone', 'body', 'created_at']),
            'candidates' => mb_strlen($this->customerSearch) >= 2 ? Customer::search($this->customerSearch)->limit(6)->get(['id', 'code', 'full_name', 'phone_primary']) : collect(),
            'identified' => $this->phone ? Customer::where('whatsapp_number', $this->phone)->first(['id', 'full_name', 'code', 'dui']) : null,
            'verificationMethod' => setting('chatbot.verification_method'),
        ]);
    }
}
