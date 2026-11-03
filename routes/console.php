<?php

use App\Enums\CampaignStatus;
use App\Jobs\RecalculateDelinquencyJob;
use App\Models\Campaign;
use App\Models\ChatbotSession;
use App\Models\CustomerVerification;
use App\Services\Messaging\CampaignService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('campaigns:dispatch-scheduled', function (CampaignService $service) {
    $due = Campaign::where('status', CampaignStatus::Scheduled)->where('scheduled_at', '<=', now())->get();
    foreach ($due as $campaign) {
        $service->start($campaign);
        $this->info("Campaña iniciada: {$campaign->name}");
    }
})->purpose('Inicia las campañas programadas cuya fecha ya llegó');

Artisan::command('delinquency:recalculate {--force : Ejecutar aunque el recálculo automático esté desactivado}', function () {
    if (! $this->option('force') && ! setting('collection.auto_recalculate_enabled')) {
        $this->warn('El recálculo automático está desactivado en Configuración › Cobranza. Use --force para ejecutarlo.');

        return;
    }
    RecalculateDelinquencyJob::dispatchSync();
    $this->info('Recálculo de estados de mora completado.');
})->purpose('Recalcula días y estados de mora según las fechas de pago');

Artisan::command('chatbot:cleanup', function () {
    $expired = CustomerVerification::where('status', 'pending')->where('expires_at', '<', now())->update(['status' => 'expired']);
    $sessions = ChatbotSession::whereNotNull('verification_expires_at')->where('verification_expires_at', '<', now())
        ->update(['customer_id' => null, 'verified_at' => null, 'verification_expires_at' => null, 'state' => 'idle']);
    $this->info("Verificaciones expiradas: {$expired}. Sesiones cerradas: {$sessions}.");
})->purpose('Expira verificaciones de identidad y sesiones del chatbot vencidas');

Artisan::command('icons:lucide', function () {
    $count = \App\Support\Icons::build();
    $this->info("Iconos Lucide precompilados: {$count} (".\App\Support\Icons::CACHE.')');
})->purpose('Precompila los iconos Lucide en un archivo PHP (mejora el tiempo de renderizado)');

Schedule::command('campaigns:dispatch-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('chatbot:cleanup')->everyFifteenMinutes();
Schedule::command('delinquency:recalculate')->dailyAt('01:00')->withoutOverlapping();
