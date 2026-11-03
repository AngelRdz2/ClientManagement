<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Contracts\WhatsAppProvider;
use App\Services\WhatsApp\Providers\MetaCloudProvider;
use App\Services\WhatsApp\Providers\SimulatorProvider;
use App\Services\WhatsApp\Providers\TwilioProvider;
use InvalidArgumentException;

/**
 * Resuelve el proveedor de WhatsApp activo según la configuración del sistema.
 */
class WhatsAppManager
{
    public const PROVIDERS = [
        'simulator' => SimulatorProvider::class,
        'meta' => MetaCloudProvider::class,
        'twilio' => TwilioProvider::class,
    ];

    /** @var array<string, WhatsAppProvider> */
    private array $resolved = [];

    public function provider(?string $name = null): WhatsAppProvider
    {
        $name ??= $this->activeName();

        if (! isset(self::PROVIDERS[$name])) {
            throw new InvalidArgumentException("Proveedor de WhatsApp desconocido: {$name}");
        }

        return $this->resolved[$name] ??= app(self::PROVIDERS[$name]);
    }

    public function activeName(): string
    {
        $name = (string) setting('whatsapp.provider', 'simulator');

        return isset(self::PROVIDERS[$name]) ? $name : 'simulator';
    }

    public function isSimulation(): bool
    {
        return $this->provider()->isSimulation();
    }

    /** @return array<string, WhatsAppProvider> */
    public function all(): array
    {
        return collect(array_keys(self::PROVIDERS))
            ->mapWithKeys(fn (string $name) => [$name => $this->provider($name)])
            ->all();
    }

    public function forget(): void
    {
        $this->resolved = [];
    }
}
