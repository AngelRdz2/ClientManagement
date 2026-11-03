<?php

namespace App\Services\WhatsApp\Contracts;

use App\Services\WhatsApp\Data\SendResult;
use App\Services\WhatsApp\Data\WebhookPayload;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrato común para cualquier proveedor de WhatsApp. El resto del sistema depende únicamente
 * de esta interfaz, de modo que cambiar de proveedor no requiere modificar la lógica de negocio.
 */
interface WhatsAppProvider
{
    /** Identificador interno: simulator | meta | twilio */
    public function name(): string;

    public function label(): string;

    /** true solo para el proveedor de simulación (nunca envía mensajes reales). */
    public function isSimulation(): bool;

    /** Indica si las credenciales necesarias están configuradas. */
    public function isConfigured(): bool;

    /** Envía un mensaje de texto libre (permitido dentro de la ventana de atención de 24 h). */
    public function sendText(string $to, string $body): SendResult;

    /**
     * Envía una plantilla aprobada por el proveedor (requerido por Meta fuera de la ventana de 24 h).
     *
     * @param  list<string>  $parameters  valores para {{1}}, {{2}}, ... del cuerpo de la plantilla
     */
    public function sendTemplate(string $to, string $templateName, string $language, array $parameters = [], ?string $fallbackBody = null): SendResult;

    /** Responde al desafío de verificación del webhook (Meta usa GET con hub.challenge). */
    public function verificationChallenge(Request $request): ?Response;

    /** Valida la firma del webhook entrante para asegurar que proviene del proveedor. */
    public function validateWebhookSignature(Request $request): bool;

    /** Convierte el cuerpo del webhook en mensajes entrantes y actualizaciones de estado. */
    public function parseWebhook(Request $request): WebhookPayload;
}
