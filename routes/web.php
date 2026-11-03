<?php

use App\Http\Controllers\ImportDownloadController;
use App\Http\Controllers\ReportExportController;
use App\Livewire;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Livewire\Auth\Login::class)->name('login');
});

Route::post('/logout', function (Request $request) {
    if ($user = $request->user()) {
        ActivityLogger::log('auth.logout', "Cierre de sesión de {$user->name}", $user);
    }
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::livewire('/', Livewire\Dashboard::class)->name('dashboard');
    Route::livewire('/perfil', Livewire\Profile::class)->name('profile');
    Route::livewire('/notificaciones', Livewire\Notifications\Index::class)->name('notifications.index');

    // Fase 2: clientes, importación y cobranza
    Route::middleware('permission:customers.import')->group(function () {
        Route::livewire('/clientes/importar', Livewire\Customers\Import::class)->name('customers.import');
        Route::get('/clientes/importar/plantilla', [ImportDownloadController::class, 'template'])->name('customers.import.template');
        Route::get('/clientes/importar/{batch}/errores', [ImportDownloadController::class, 'errors'])->name('customers.import.errors');
    });
    Route::middleware('permission:customers.manage')->group(function () {
        Route::livewire('/clientes/nuevo', Livewire\Customers\Form::class)->name('customers.create');
        Route::livewire('/clientes/{customer}/editar', Livewire\Customers\Form::class)->name('customers.edit');
        Route::livewire('/cobranza/actualizar-estados', Livewire\Delinquency\Updates::class)->name('delinquency.updates');
        Route::livewire('/clientes/grupos', Livewire\Groups\Index::class)->name('groups.index');
    });
    Route::middleware('permission:customers.view')->group(function () {
        Route::livewire('/clientes', Livewire\Customers\Index::class)->name('customers.index');
        Route::livewire('/clientes/{customer}', Livewire\Customers\Show::class)->name('customers.show')->whereNumber('customer');
    });
    Route::livewire('/cobranza/estados', Livewire\Delinquency\Statuses::class)->middleware('permission:delinquency.manage')->name('delinquency.index');
    Route::livewire('/cobranza/historial', Livewire\Delinquency\History::class)->name('delinquency.history');

    // Fase 3: mensajería
    Route::livewire('/plantillas', Livewire\Templates\Index::class)->middleware('permission:templates.manage')->name('templates.index');
    Route::middleware('permission:campaigns.manage')->group(function () {
        Route::livewire('/campanas', Livewire\Campaigns\Index::class)->name('campaigns.index');
        Route::livewire('/campanas/nueva', Livewire\Campaigns\Builder::class)->name('campaigns.create');
        Route::livewire('/campanas/{campaign}', Livewire\Campaigns\Show::class)->whereNumber('campaign')->name('campaigns.show');
        Route::livewire('/campanas/{campaign}/editar', Livewire\Campaigns\Builder::class)->whereNumber('campaign')->name('campaigns.edit');
        Route::livewire('/promociones', Livewire\Promotions\Index::class)->name('promotions.index');
        Route::livewire('/promociones/nueva', Livewire\Promotions\Builder::class)->name('promotions.create');
        Route::livewire('/promociones/{campaign}', Livewire\Campaigns\Show::class)->whereNumber('campaign')->name('promotions.show');
        Route::livewire('/promociones/{campaign}/editar', Livewire\Promotions\Builder::class)->whereNumber('campaign')->name('promotions.edit');
    });
    Route::livewire('/mensajes', Livewire\Messages\Index::class)->middleware('permission:messages.send')->name('messages.index');

    // Fase 4: conversaciones y chatbot
    Route::middleware('permission:conversations.attend')->group(function () {
        Route::livewire('/conversaciones', Livewire\Conversations\Inbox::class)->name('conversations.index');
        Route::livewire('/simulador', Livewire\Simulator::class)->name('simulator');
    });
    Route::livewire('/chatbot', Livewire\Chatbot\Index::class)->middleware('permission:settings.manage')->name('chatbot.index');

    // Fase 5: reportes, usuarios y configuración
    Route::middleware('permission:reports.view')->group(function () {
        Route::livewire('/reportes', Livewire\Reports\Index::class)->name('reports.index');
        Route::get('/reportes/exportar', ReportExportController::class)->name('reports.export');
    });
    Route::livewire('/usuarios', Livewire\Users\Index::class)->middleware('permission:users.manage')->name('users.index');
    Route::livewire('/configuracion', Livewire\Settings\Index::class)->middleware('permission:settings.manage')->name('settings.index');
});
