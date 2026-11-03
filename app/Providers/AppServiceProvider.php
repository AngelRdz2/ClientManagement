<?php

namespace App\Providers;

use App\Services\Settings;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(WhatsAppManager::class);
    }

    public function boot(): void
    {
        // Detecta consultas N+1 y asignaciones masivas silenciosas durante el desarrollo.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Límite de envíos al proveedor de WhatsApp (configurable desde el sistema).
        RateLimiter::for('whatsapp-outbound', function () {
            return Limit::perMinute(max(1, (int) setting('whatsapp.rate_limit_per_minute', 60)));
        });

        // Límite de webhooks entrantes por IP.
        RateLimiter::for('webhooks', fn ($request) => Limit::perMinute(600)->by($request->ip()));
    }
}
