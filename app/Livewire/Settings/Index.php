<?php

namespace App\Livewire\Settings;

use App\Services\ActivityLogger;
use App\Services\Settings;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\Phone;
use App\Support\Webhooks;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Configuración')]
class Index extends Component
{
    #[Url(except: 'company')]
    public string $tab = 'company';

    public array $values = [];

    /** Credenciales secretas: solo se envían si el usuario escribe un valor nuevo. */
    public array $secrets = [];

    public array $schedule = [];

    public string $testNumber = '';

    public const TABS = [
        'company' => ['Empresa', 'building-2', ['company.name', 'company.phone', 'company.email', 'company.address', 'company.website']],
        'whatsapp' => ['WhatsApp', 'smartphone', ['whatsapp.provider', 'whatsapp.rate_limit_per_minute', 'whatsapp.simulator_failure_rate', 'whatsapp.meta_phone_number_id', 'whatsapp.meta_business_account_id', 'whatsapp.twilio_account_sid', 'whatsapp.twilio_from_number']],
        'hours' => ['Horario de atención', 'clock', ['hours.timezone']],
        'notifications' => ['Notificaciones', 'bell', ['notifications.agent_requested', 'notifications.new_conversation', 'notifications.campaign_finished', 'notifications.import_failed', 'notifications.messages_failed', 'notifications.conversation_assigned']],
        'communication' => ['Comunicación', 'message-circle', ['communication.opt_out_keywords', 'communication.opt_out_message', 'communication.collection_send_from', 'communication.collection_send_until', 'communication.min_hours_between_collection_messages']],
        'collection' => ['Cobranza', 'hand-coins', ['collection.auto_recalculate_enabled']],
    ];

    public const SECRET_KEYS = ['whatsapp.meta_access_token', 'whatsapp.meta_app_secret', 'whatsapp.meta_verify_token', 'whatsapp.twilio_auth_token'];

    public const DAYS = ['mon' => 'Lunes', 'tue' => 'Martes', 'wed' => 'Miércoles', 'thu' => 'Jueves', 'fri' => 'Viernes', 'sat' => 'Sábado', 'sun' => 'Domingo'];

    public function mount(): void
    {
        $this->authorize('settings.manage');
        if (! isset(self::TABS[$this->tab])) {
            $this->tab = 'company';
        }
        $this->load();
    }

    protected function load(): void
    {
        foreach (self::TABS as [, , $keys]) {
            foreach ($keys as $key) {
                $this->values[$this->k($key)] = setting($key);
            }
        }
        foreach (self::SECRET_KEYS as $key) {
            $this->secrets[$this->k($key)] = '';
        }
        $saved = (array) setting('hours.schedule', []);
        foreach (self::DAYS as $day => $label) {
            $range = $saved[$day] ?? null;
            $this->schedule[$day] = ['open' => is_array($range), 'from' => $range[0] ?? '08:00', 'to' => $range[1] ?? '17:00'];
        }
    }

    /** Las claves con punto no se pueden usar directamente en wire:model. */
    private function k(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function save(Settings $settings): void
    {
        $rules = match ($this->tab) {
            'company' => ['values.company__name' => ['required', 'string', 'max:150'], 'values.company__email' => ['nullable', 'email'], 'values.company__phone' => ['nullable', 'string', 'max:30'], 'values.company__address' => ['nullable', 'string', 'max:300'], 'values.company__website' => ['nullable', 'url']],
            'whatsapp' => ['values.whatsapp__provider' => ['required', 'in:'.implode(',', array_keys(WhatsAppManager::PROVIDERS))], 'values.whatsapp__rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:1000'], 'values.whatsapp__simulator_failure_rate' => ['required', 'integer', 'min:0', 'max:100']],
            'hours' => ['schedule.*.from' => ['required', 'date_format:H:i'], 'schedule.*.to' => ['required', 'date_format:H:i']],
            'communication' => ['values.communication__opt_out_keywords' => ['required', 'string', 'max:500'], 'values.communication__collection_send_from' => ['required', 'date_format:H:i'], 'values.communication__collection_send_until' => ['required', 'date_format:H:i', 'after:values.communication__collection_send_from'], 'values.communication__min_hours_between_collection_messages' => ['required', 'integer', 'min:0', 'max:720']],
            default => [],
        };
        if ($rules !== []) {
            $this->validate($rules, attributes: ['values.company__name' => 'nombre de la empresa', 'values.communication__collection_send_until' => 'hora de fin']);
        }

        foreach (self::TABS[$this->tab][2] as $key) {
            if ($settings->isFromEnv($key)) {
                continue;
            }
            $settings->set($key, $this->values[$this->k($key)] ?? null);
        }

        if ($this->tab === 'whatsapp') {
            foreach (self::SECRET_KEYS as $key) {
                $value = trim((string) ($this->secrets[$this->k($key)] ?? ''));
                if ($value !== '' && ! $settings->isFromEnv($key)) {
                    $settings->set($key, $value); // se almacena cifrado
                }
            }
            app(WhatsAppManager::class)->forget();
        }

        if ($this->tab === 'hours') {
            $settings->set('hours.schedule', collect($this->schedule)->map(fn ($d) => $d['open'] ? [$d['from'], $d['to']] : null)->all());
        }

        ActivityLogger::log('settings.updated', 'Actualizó la configuración: '.self::TABS[$this->tab][0]);
        $this->load();
        $this->dispatch('toast', type: 'success', message: 'Configuración guardada');
    }

    public function clearSecret(string $key, Settings $settings): void
    {
        abort_unless(in_array($key, self::SECRET_KEYS, true), 404);
        $settings->set($key, null);
        ActivityLogger::log('settings.secret_cleared', "Eliminó la credencial {$key}");
        $this->dispatch('toast', type: 'success', message: 'Credencial eliminada');
    }

    public function sendTest(WhatsAppManager $whatsapp): void
    {
        $this->validate(['testNumber' => ['required', 'regex:/^[\d\s\-\+\(\)]{8,20}$/']], attributes: ['testNumber' => 'número de prueba']);
        $provider = $whatsapp->provider();
        $result = $provider->sendText((string) Phone::normalize($this->testNumber), 'Mensaje de prueba de '.setting('company.name').' ('.$provider->label().'). Si lo recibiste, la integración funciona correctamente.');
        ActivityLogger::log('settings.whatsapp_test', 'Envió un mensaje de prueba por '.$provider->label().' a '.Phone::mask($this->testNumber), null, ['success' => $result->success, 'error' => $result->errorMessage]);

        $result->success
            ? $this->dispatch('alert', type: 'success', title: 'Mensaje enviado', message: ($provider->isSimulation() ? 'Simulación: no se envió a WhatsApp real. ' : '').'ID del proveedor: '.$result->providerMessageId)
            : $this->dispatch('alert', type: 'error', title: 'No se pudo enviar', message: "{$result->errorCode}: {$result->errorMessage}");
    }

    public function render(Settings $settings, WhatsAppManager $whatsapp)
    {
        $secretStatus = [];
        foreach (self::SECRET_KEYS as $key) {
            $secretStatus[$key] = ['env' => $settings->isFromEnv($key), 'set' => filled($settings->get($key))];
        }

        return view('livewire.settings.index', [
            'tabs' => self::TABS,
            'providers' => $whatsapp->all(),
            'secretStatus' => $secretStatus,
            'envKeys' => collect(Settings::ENV_OVERRIDES)->filter(fn ($env, $key) => $settings->isFromEnv($key))->keys()->all(),
            'webhooks' => ['meta' => Webhooks::url('meta'), 'twilio' => Webhooks::url('twilio')],
            'webhooksPublic' => Webhooks::isPublic(),
        ]);
    }
}
