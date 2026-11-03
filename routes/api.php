<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Webhooks de proveedores de WhatsApp (sin sesión ni CSRF; se validan por firma del proveedor).
|   Meta:   https://<dominio>/api/webhooks/whatsapp/meta    (GET verificación + POST eventos)
|   Twilio: https://<dominio>/api/webhooks/whatsapp/twilio  (POST mensajes y StatusCallback)
*/
Route::middleware('throttle:webhooks')->prefix('webhooks/whatsapp')->group(function () {
    Route::get('{provider}', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
    Route::post('{provider}', [WhatsAppWebhookController::class, 'receive'])->name('webhooks.whatsapp');
});
