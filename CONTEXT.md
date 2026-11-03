# CONTEXT — Traspaso para continuar el proyecto en otra PC

> Documento de contexto para retomar CobraCRM (en otra máquina o con otra sesión de Claude Code).
> Complementa a `README.md` (instalación y uso) y `PROGRESS.md` (fases y decisiones).
> **No contiene secretos**: contraseñas, tokens y `APP_KEY` viven solo en `.env` / base de datos.
>
> Para Claude Code en una sesión nueva: *"Lee CONTEXT.md, README.md y PROGRESS.md antes de hacer cambios."*

Última actualización: 2026-11-03

---

## 1. Qué es y en qué estado está

CRM en Laravel para **clientes, cobranza (estados de mora), campañas masivas de WhatsApp, promociones, chatbot por
reglas y bandeja de conversaciones con asesores**. Español (El Salvador), interfaz SaaS en Livewire.

**Estado:** las 5 fases del requerimiento original están **completas** y probadas, más mejoras posteriores
(ver §8). Suite: **62 pruebas / 410 aserciones, todas pasan** (`php artisan test`).

Repositorio: `https://github.com/AngelRdz2/ClientManagement` (rama principal, commit inicial "First Commit").

---

## 2. Stack (versiones reales instaladas)

| Pieza | Versión | Nota |
|---|---|---|
| PHP | 8.4 | |
| Laravel | **13.40** | Se pidió 14, pero **no existe estable**; el usuario aprobó usar 13. |
| Livewire | 4.4 | Rutas con `Route::livewire()`. Alpine viene incluido (importado vía ESM en `resources/js/app.js`). |
| Tailwind | 4 (Vite 7) | Tema en `resources/css/app.css` (`navy-*`, `.form-input`, `.table-base`). |
| Laravel Excel | 4.0 | **Firmas tipadas** (PhpSpreadsheet 5). `WithMultipleSheets` requiere implementar `Export`. |
| Spatie Permission | 8.3 | Middleware `permission:` registrado en `bootstrap/app.php`. |
| Iconos Lucide | `mallardduck/blade-lucide-icons` 2 | **Solo como fuente de SVG**; los componentes del paquete están desactivados (ver §6). |
| SweetAlert2 / Chart.js | 11 / 4 | `$confirm(...)` (magic de Alpine), eventos `toast` / `alert`. |
| MySQL | 8 | BD `client_management` (utf8mb4). Pruebas usan SQLite en memoria. |
| Servidor | Laravel Herd (Windows) | Dominio `http://client-management.test`. |

---

## 3. Montar el proyecto en otra PC

```bash
git clone https://github.com/AngelRdz2/ClientManagement.git client-management
cd client-management
composer install          # también genera bootstrap/cache/lucide-icons.php (icons:lucide)
npm install && npm run build
copy .env.example .env    # ajustar DB_USERNAME / DB_PASSWORD de esa PC
```

1. **Herd:** si la carpeta no está dentro de una ruta "parked" de Herd, ejecutar `herd link client-management`
   en la carpeta del proyecto para obtener `http://client-management.test`.
2. **Base de datos:** crear `client_management` (utf8mb4_unicode_ci).
3. **Elegir una opción de datos:**
   - **A. Datos nuevos de demostración:** `php artisan key:generate` y `php artisan migrate --seed`.
     Se pierden los datos de esta PC (cliente 151, campaña de prueba, credenciales de Twilio, SID de plantilla).
   - **B. Llevarse los datos de esta PC:** exportar aquí con
     `mysqldump -u <usuario> -p client_management > cobracrm.sql`, importar allá y luego `php artisan migrate`.
     **Copiar también el `APP_KEY` del `.env` de esta PC** (por canal privado, nunca por Git): el Auth Token de
     Twilio está **cifrado en la BD con ese APP_KEY**. Con otro APP_KEY no se puede descifrar y habrá que volver
     a escribirlo en Configuración › WhatsApp.
4. `php artisan view:cache` (evita una carrera de compilación de vistas en Windows, ver §6).
5. Procesos en segundo plano (necesarios para campañas, importaciones grandes y campañas programadas):
   `composer dev` **o** por separado `php artisan queue:work --queue=campaigns,whatsapp,default` y `php artisan schedule:work`.

Usuarios demo (opción A o dump de esta PC): `admin@crm.test`, `supervisor@crm.test`, `asesor@crm.test`,
`asesor2@crm.test`, `cobranza@crm.test` — contraseña `password` (cambiar en producción).

---

## 4. Estado de configuración en la PC original (datos, no código)

- **Proveedor de WhatsApp activo: Twilio** (envíos REALES). Account SID, número remitente y Auth Token se guardaron
  desde la UI en `system_settings` (el token cifrado). Las variables `TWILIO_*` del `.env` están vacías.
  Si se definen en `.env`, **tienen prioridad** sobre la BD.
- **Recepción de mensajes (webhooks):** sin URL pública, Twilio no puede entregar las respuestas de los clientes
  (en WhatsApp aparece *"Configure your WhatsApp Sandbox's Inbound URL"*). Abrir un túnel (`herd share`), poner su URL
  en `WHATSAPP_PUBLIC_URL` (`.env`) + `php artisan config:clear`, y pegar
  `https://<túnel>/api/webhooks/whatsapp/twilio` en Twilio › Sandbox settings › "When a message comes in" (POST).
  Al 2026-11-03 esto **aún no estaba configurado**: el envío a Miguel Amaya funcionó, pero su respuesta "Hola" no llegó.
- Plantilla **"Cobranza · Atención urgente"** (id 5) tiene Content SID de Twilio
  `HX8bd92f91d571d5d819df4b64f2602ec6` (campo `twilio_content_sid`). Sus variables, en orden:
  `{{1}}` nombre · `{{2}}` código de cliente · `{{3}}` saldo · `{{4}}` teléfono empresa · `{{5}}` empresa.
  Está pendiente confirmar que la plantilla creada en Twilio tenga exactamente ese orden y que esté *Approved*.
- Cliente **151 "Miguel Amaya"** fue creado manualmente por el usuario (sin artículos, saldo $0.00).
- Existe una campaña de prueba "Cobranza noviembre - prueba" (enviada con el simulador, 47 entregados / 1 fallido).

---

## 5. Mapa del código (dónde está cada cosa)

```
app/
├── Enums/                      Estados del sistema (campañas, mensajes, conversaciones…). Los estados de MORA NO son enum: están en BD.
├── Models/                     Eloquent con atributos #[Fillable]. CustomerStatusHistory es inmutable.
├── Livewire/                   Pantallas (Customers, Delinquency, Campaigns, Promotions = subclases con mode='promotion',
│                               Templates, Messages, Conversations\Inbox, Simulator, Chatbot, Reports, Users, Settings…)
├── Services/
│   ├── WhatsApp/               Contracts\WhatsAppProvider + Providers (Simulator, MetaCloud, Twilio) + WhatsAppManager
│   ├── Messaging/              MessageSender (único punto de salida, idempotencia), CampaignService, CampaignAudience,
│   │                           TemplateRenderer (variables {{...}}), BusinessHours
│   ├── Conversations/          InboundMessageService (webhooks/simulador), ConversationService (asesores, mensaje directo),
│   │                           StatusUpdateService (sent→delivered→read)
│   ├── Chatbot/                IntentDetector (palabras clave en BD) + ChatbotEngine (verificación DUI + 2.º factor)
│   ├── Import/                 CustomerImportService + ImportColumns (estructura del Excel)
│   ├── Reports/ReportService   10 reportes + exportación
│   ├── DelinquencyService      Cambios de estado con historial, recálculo por fechas
│   ├── PaymentService, Settings (config en BD + cifrado), ActivityLogger, NotificationService, DashboardMetrics
├── Support/                    Icons (SVG precompilados), Dui (dígito verificador), Phone, Money, Permissions, Navigation
├── Jobs/                       SendWhatsAppMessageJob (rate limit), ProcessCampaignJob, ProcessCustomerImportJob, RecalculateDelinquencyJob
├── Http/Controllers/           WhatsAppWebhookController, ImportDownloadController, ReportExportController
config/crm.php                  Valores por defecto de TODA la configuración administrable (clave => [grupo, tipo, default])
config/whatsapp.php             Credenciales desde .env
routes/web.php | api.php        Web por fases | webhooks /api/webhooks/whatsapp/{meta|twilio|simulator}
routes/console.php              Comandos: campaigns:dispatch-scheduled, delinquency:recalculate, chatbot:cleanup, icons:lucide + schedule
resources/views/components/     UI reutilizable: button, card, modal, field, input, select, badge, status-pill, stat-card, icon, wa-bubble…
tests/Feature/Phase{1..5}*, DirectMessageTest   Pruebas por fase
```

---

## 6. Convenciones y trampas conocidas (importante antes de editar)

1. **Iconos:** usar `<x-icon name="users" class="size-4" />` o `<x-icon :name="$var" />`.
   **No usar** `<x-lucide-*>` ni `<x-dynamic-component :component="'lucide-...'">` (costaban ~1 ms por ícono y hacían
   las páginas 2–3 veces más lentas). Los componentes de Blade Icons están desactivados en `config/blade-icons.php`
   (`disabled => true`, `default => null`; `default` debe ser null porque el paquete registraba su propio `<x-icon>`).
   Tras usar un ícono nuevo: `php artisan icons:lucide` (sin eso funciona, pero leyendo del disco).
2. **`@js()` NO funciona dentro de atributos de componentes Blade** (`<x-button x-on:click="... @js(...) ...">`):
   no se compila y rompe Alpine. Dentro de `<x-...>` usar `{{ \Illuminate\Support\Js::from(...) }}`.
   En etiquetas HTML normales `@js()` sí funciona.
3. **Literales `{{ }}` en Blade:** para mostrar `{{variable}}` como texto usar `@{{variable}}` o entidades
   `&#123;&#123;`. En seeders con comillas dobles, `${{` debe escribirse `\${{`.
4. **Claves con punto en wire:model:** la configuración usa claves como `company.name`; en Livewire se mapean a
   `company__name` (ver `Settings\Index`, `Chatbot\Index`). `config("crm.settings.$key")` NO sirve (lo trata como
   ruta anidada): usar `config('crm.settings')[$key]`.
5. **Windows / Herd:** error `rename(... .tmp ...): Acceso denegado` = dos peticiones compilando la misma vista.
   Solución: `php artisan view:cache`. Las pruebas compilan vistas en `storage/framework/testing/views` (phpunit.xml)
   para no chocar con el navegador.
6. **N+1:** `Model::preventLazyLoading()` está activo fuera de producción. Precargar relaciones con `with()`.
7. **Livewire:** validar con reglas vacías lanza `MissingRulesException` (proteger con `if ($rules !== [])`).
   Las matrices editables usan IDs como claves, no nombres con espacios.
8. **Mensajes salientes:** siempre pasar por `MessageSender` (idempotencia + proveedor correcto). Las conversaciones
   `is_simulated` nunca salen por un proveedor real. Plantillas aprobadas: `MessageTemplate::approvedIdFor($provider)`
   (Meta = `provider_template_name` + idioma, Twilio = `twilio_content_sid`); variables en orden de aparición → `{{1}}…`.
9. **Estados de mora:** nunca hardcodear nombres; usar banderas `is_solvent`, `is_critical`, `is_auto_assignable`,
   `exclude_from_campaigns`. Cambios siempre vía `DelinquencyService::changeStatus()` (escribe el historial).
10. Formato: `vendor/bin/pint`. Textos de la UI y comentarios en español.

---

## 7. Decisiones técnicas clave

Ver la tabla completa en `PROGRESS.md`. Resumen: Laravel 13 (14 no existe); estados de mora en BD; totales
desnormalizados en `customers` (se recalculan al guardar artículos); historial de mora inmutable; `messages` (registro
del proveedor) + `conversation_messages` (línea de tiempo con eventos y notas); respuestas del bot y asesores en la misma
petición y campañas por cola; destinatarios calculados al iniciar la campaña; credenciales: `.env` primero, si no,
cifradas en BD.

---

## 8. Historial de la sesión de desarrollo (resumen)

1. Fases 1–5 completas (ver `PROGRESS.md`): fundación, clientes/importación/mora, mensajería/campañas, chatbot/bandeja,
   reportes/webhooks/configuración/usuarios.
2. **Rendimiento:** se perfiló dentro de Herd. Arranque 10–15 ms, SQL 25–55 ms, renderizado 300–500 ms por los iconos.
   Con `<x-icon>` precompilado: páginas de 300–450 ms → 100–200 ms (régimen estable).
3. **Mensaje individual desde la ficha del cliente** (botón "Enviar mensaje"): texto libre o plantilla con datos del
   cliente, vista previa, se registra en la conversación, opción de asignarse la conversación, anti-doble envío,
   consentimiento de promociones y ventana de 24 h.
4. **Plantillas aprobadas por proveedor:** el editor de plantillas tiene secciones separadas para Meta (nombre + idioma)
   y Twilio (Content SID `HX` + 32 caracteres, validado). Migración `2026_11_03_170000_add_twilio_content_sid…`
   movió el SID que estaba en el campo de Meta.
5. **Webhooks detrás de túnel/proxy:** `WHATSAPP_PUBLIC_URL` + `App\Support\Webhooks`. La firma de Twilio se valida
   contra la URL local y la pública; el `StatusCallback` solo se envía si la URL es HTTPS pública. Configuración ›
   WhatsApp muestra un aviso con los pasos cuando el proveedor es real y la URL no es pública.
6. Se corrigió la codificación de `README.md` y `PROGRESS.md` (un comando de PowerShell 5.1 los había guardado con
   caracteres corruptos tipo "Ã³"; esa corrupción también está en el primer commit de GitHub).

---

## 9. Pendientes / siguientes pasos sugeridos

- [ ] Confirmar en Twilio que la plantilla `HX8bd9…2ec6` está *Approved* y que sus variables coinciden con §4; probar
      el envío con un cliente que tenga saldo (no el 151).
- [ ] Registrar Content SID / nombre Meta en las demás plantillas que se quieran usar fuera de la ventana de 24 h
      (en particular las de cada estado de mora para campañas de cobranza).
- [ ] Configurar el webhook de Twilio (“When a message comes in”) con dominio público HTTPS
      (`https://<dominio>/api/webhooks/whatsapp/twilio`); en local se puede usar `herd share`.
- [ ] Producción: Supervisor para `queue:work`, cron para `schedule:run`, `php artisan optimize`, cambiar contraseñas demo.
- [ ] Opcional: ocultar o restringir el "Simulador" cuando el proveedor sea real; activar el recálculo automático de
      mora (Configuración › Cobranza) solo si las fechas de pago son confiables.
