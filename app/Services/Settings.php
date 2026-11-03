<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración administrable del sistema (tabla system_settings) con valores por defecto en config/crm.php.
 *
 * Credenciales: las variables de entorno tienen prioridad. Si no existen, se usa el valor guardado
 * desde la pantalla de configuración, que se almacena cifrado con APP_KEY. Nunca se guardan en código.
 */
class Settings
{
    private const CACHE_KEY = 'system_settings.all';

    /** Variables de entorno que sobrescriben credenciales. */
    public const ENV_OVERRIDES = [
        'whatsapp.meta_phone_number_id' => 'WHATSAPP_META_PHONE_NUMBER_ID',
        'whatsapp.meta_business_account_id' => 'WHATSAPP_META_BUSINESS_ACCOUNT_ID',
        'whatsapp.meta_access_token' => 'WHATSAPP_META_ACCESS_TOKEN',
        'whatsapp.meta_app_secret' => 'WHATSAPP_META_APP_SECRET',
        'whatsapp.meta_verify_token' => 'WHATSAPP_META_VERIFY_TOKEN',
        'whatsapp.twilio_account_sid' => 'TWILIO_ACCOUNT_SID',
        'whatsapp.twilio_auth_token' => 'TWILIO_AUTH_TOKEN',
        'whatsapp.twilio_from_number' => 'TWILIO_WHATSAPP_FROM',
    ];

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::ENV_OVERRIDES[$key])) {
            $envValue = config('whatsapp.env.'.self::ENV_OVERRIDES[$key]);
            if (filled($envValue)) {
                return $envValue;
            }
        }

        $all = $this->all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        $definition = config('crm.settings')[$key] ?? null;

        return $definition[2] ?? $default;
    }

    /** Indica si una credencial proviene de una variable de entorno. */
    public function isFromEnv(string $key): bool
    {
        return isset(self::ENV_OVERRIDES[$key]) && filled(config('whatsapp.env.'.self::ENV_OVERRIDES[$key]));
    }

    public function set(string $key, mixed $value): void
    {
        $definition = config('crm.settings')[$key] ?? ['general', 'string', null];
        [$group, $type] = $definition;

        $stored = match ($type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            default => $value === null ? null : (string) $value,
        };

        $encrypted = $type === 'secret';
        if ($encrypted && filled($stored)) {
            $stored = Crypt::encryptString($stored);
        }

        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'type' => $type, 'value' => $stored, 'is_encrypted' => $encrypted],
        );

        $this->flush();
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        if (! Schema::hasTable('system_settings')) {
            return $this->loaded = [];
        }

        $rows = Cache::rememberForever(self::CACHE_KEY, fn () => SystemSetting::query()
            ->get(['key', 'value', 'type', 'is_encrypted'])
            ->map(fn (SystemSetting $s) => $s->only(['key', 'value', 'type', 'is_encrypted']))
            ->all());

        $values = [];
        foreach ($rows as $row) {
            $values[$row['key']] = $this->cast($row['value'], $row['type'], (bool) $row['is_encrypted']);
        }

        return $this->loaded = $values;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    private function cast(?string $value, string $type, bool $encrypted): mixed
    {
        if ($encrypted && filled($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException) {
                return null;
            }
        }

        return match ($type) {
            'bool' => $value === '1',
            'int' => (int) $value,
            'json' => $value ? json_decode($value, true) : null,
            default => $value,
        };
    }
}
