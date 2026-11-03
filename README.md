# CobraCRM â€” Clientes, cobranza y WhatsApp

Plataforma CRM para administrar clientes, artÃ­culos y saldos, controlar estados de mora configurables, enviar campaÃ±as
masivas de cobranza y promociones por WhatsApp, atender clientes con un chatbot basado en reglas y transferir
conversaciones a asesores humanos.

> **Modo simulaciÃ³n por defecto.** El sistema arranca con un proveedor de WhatsApp **simulado**: registra los envÃ­os y
> permite probar todo el flujo sin enviar mensajes reales ni pagar por ellos. La interfaz lo indica en todo momento.
> Para conectar WhatsApp Cloud API (Meta) o Twilio solo se cambia el proveedor en **ConfiguraciÃ³n â€º WhatsApp**.

---

## Stack

| Componente | VersiÃ³n |
|---|---|
| PHP | 8.4 |
| Laravel | **13.40** (Laravel 14 aÃºn no estÃ¡ publicado; ver *Decisiones*) |
| Livewire (incluye Alpine.js) | 4.4 |
| Tailwind CSS | 4.x (Vite 7) |
| Laravel Excel (Maatwebsite) | 4.0 (PhpSpreadsheet 5) |
| Spatie Laravel Permission | 8.3 |
| Lucide Icons | `mallardduck/blade-lucide-icons` 2.0 |
| SweetAlert2 / Chart.js | 11 / 4 |
| Base de datos | MySQL 8 |
| Servidor local | Laravel Herd (Windows) |

---

## InstalaciÃ³n (Herd en Windows)

```bash
cd C:\Users\MiguelAmaya\PhpstormProjects\client-management
composer install
npm install
copy .env.example .env          # ajustar DB_USERNAME / DB_PASSWORD
php artisan key:generate
```

1. Crear la base de datos:
   `CREATE DATABASE client_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Migrar y cargar datos base + demostraciÃ³n:
   ```bash
   php artisan migrate --seed
   ```
   En producciÃ³n: `php artisan migrate --force && php artisan db:seed --class=ProductionSeeder` (sin clientes demo).
3. Compilar el frontend: `npm run build` (o `npm run dev` mientras desarrollas).
4. Abrir **http://client-management.test** (Herd sirve automÃ¡ticamente la carpeta `PhpstormProjects`).

### Procesos en segundo plano (necesarios para campaÃ±as e importaciones grandes)

Herd sirve la web, pero las colas y el programador de tareas se ejecutan aparte:

```bash
php artisan queue:work --queue=campaigns,whatsapp,default   # envÃ­os de campaÃ±as, importaciones
php artisan schedule:work                                    # campaÃ±as programadas, recÃ¡lculo de mora, limpieza
```

O todo junto (servidor, colas, scheduler, logs y Vite): `composer dev`.
En producciÃ³n, usar Supervisor para `queue:work` y un cron `* * * * * php artisan schedule:run`.

### Usuarios de demostraciÃ³n

ContraseÃ±a de todos: `password` (cÃ¡mbiala en producciÃ³n).

| Correo | Rol |
|---|---|
| admin@crm.test | Administrador |
| supervisor@crm.test | Supervisor |
| asesor@crm.test Â· asesor2@crm.test | Asesor |
| cobranza@crm.test | Operador de cobranza |

El cliente demo **MarÃ­a Fernanda LÃ³pez HernÃ¡ndez** (DUI `01234567-8`, tel. `7012-3456`) reproduce el ejemplo del
chatbot: 2 artÃ­culos, saldo total $430.00.

---

## MÃ³dulos

| MÃ³dulo | Ruta | DescripciÃ³n |
|---|---|---|
| Dashboard | `/` | KPIs de cartera, mora, mensajerÃ­a y conversaciones; grÃ¡ficos y alerta de clientes que esperan un asesor. |
| Clientes | `/clientes` | BÃºsqueda por nombre, DUI, telÃ©fono o cÃ³digo; ficha con resumen financiero, artÃ­culos, pagos, historial de mora, mensajes y conversaciones. |
| ArtÃ­culos y pagos | ficha del cliente | Alta/ediciÃ³n de artÃ­culos (contrato, cuotas, saldo, prÃ³xima fecha) y registro de pagos que actualizan saldo y cuotas. |
| Mensaje individual | ficha del cliente â€º *Enviar mensaje* | Mensaje de WhatsApp a un solo cliente (texto libre o plantilla rellenada con sus datos), con vista previa. Queda en su conversaciÃ³n; opcionalmente se asigna al usuario para atender la respuesta. Respeta consentimiento de promociones y la ventana de 24 h. |
| Estados de mora | `/cobranza/estados` | Estados administrables: dÃ­as, color, prioridad, plantilla, envÃ­os automÃ¡ticos, exclusiÃ³n de campaÃ±as, solvente/crÃ­tico. |
| Actualizar estados | `/cobranza/actualizar-estados` | Cambio manual individual o masivo con motivo, y recÃ¡lculo automÃ¡tico por fechas de pago. |
| Historial de mora | `/cobranza/historial` | Registro inmutable: estado anterior/nuevo, fecha, usuario, origen y motivo. |
| Importar Excel | `/clientes/importar` | XLSX/CSV con plantilla descargable, previsualizaciÃ³n, validaciÃ³n por fila, duplicados, reporte de errores y cola. |
| Plantillas | `/plantillas` | Plantillas por categorÃ­a con variables dinÃ¡micas y previsualizaciÃ³n con un cliente real. |
| CampaÃ±as de cobranza | `/campanas` | SegmentaciÃ³n, plantilla por estado de mora, previsualizaciÃ³n, programaciÃ³n y resultados por destinatario. |
| Promociones | `/promociones` | MÃ³dulo separado: solo clientes con consentimiento; plantillas promocionales. |
| Historial de mensajes | `/mensajes` | Todos los mensajes enviados/recibidos con estado de entrega y reintento de fallidos. |
| Conversaciones | `/conversaciones` | Bandeja estilo WhatsApp Web (3 columnas): tomar, asignar, notas internas, devolver al bot, cerrar. |
| Simulador WhatsApp | `/simulador` | Escribe como cliente y observa al bot; simula errores de envÃ­o. |
| Chatbot | `/chatbot` | Intenciones, palabras clave, respuestas y mÃ©todo de verificaciÃ³n de identidad. |
| Reportes | `/reportes` | 10 reportes con filtro de fechas y exportaciÃ³n a Excel. |
| Usuarios y roles | `/usuarios` | Usuarios, roles y matriz de permisos editable. |
| ConfiguraciÃ³n | `/configuracion` | Empresa, WhatsApp y credenciales, horario, notificaciones, comunicaciÃ³n y cobranza. |

### Roles y permisos iniciales

| Permiso | Admin | Supervisor | Asesor | Operador de cobranza |
|---|:-:|:-:|:-:|:-:|
| Consultar clientes | âœ“ | âœ“ | âœ“ | âœ“ |
| Crear y editar clientes | âœ“ | âœ“ | | âœ“ |
| Importar Excel | âœ“ | âœ“ | | âœ“ |
| Administrar estados de mora | âœ“ | âœ“ | | |
| Crear campaÃ±as | âœ“ | âœ“ | | âœ“ |
| Enviar mensajes | âœ“ | âœ“ | âœ“ | âœ“ |
| Atender conversaciones | âœ“ | âœ“ | âœ“ | |
| Administrar plantillas | âœ“ | âœ“ | | âœ“ |
| Consultar reportes | âœ“ | âœ“ | | âœ“ |
| Administrar usuarios / configuraciÃ³n | âœ“ | | | |

Se modifican desde **Usuarios y roles â€º Roles y permisos**.

---

## Estructura del Excel de importaciÃ³n

Una fila por artÃ­culo/contrato. Si un cliente tiene varios artÃ­culos, se repiten sus datos (mismo cÃ³digo y DUI).
Descarga la plantilla desde la pantalla de importaciÃ³n (incluye hojas *Instrucciones* y *Estados de mora*).

| Columna | Oblig. | DescripciÃ³n |
|---|:-:|---|
| `codigo_cliente` | âœ“ | Identifica al cliente para crear o actualizar. |
| `nombre_completo` | âœ“ | |
| `dui` | âœ“ | 9 dÃ­gitos (`00000000-0`), se valida el dÃ­gito verificador. |
| `telefono_principal` | âœ“ | NÃºmero con WhatsApp (8 dÃ­gitos o con cÃ³digo de paÃ­s). |
| `telefono_secundario`, `correo`, `direccion`, `observaciones` | | |
| `fecha_registro` | | `dd/mm/aaaa` (por defecto, la fecha de importaciÃ³n). |
| `estado_cliente` | | `activo` / `inactivo`. |
| `estado_mora` | | CÃ³digo o nombre del estado (ej. `MORA_30`). |
| `dias_mora` | | Si no hay `estado_mora`, el estado se calcula con estos dÃ­as. |
| `acepta_promociones` | | `si` / `no` (registra fecha y origen del consentimiento). |
| `codigo_articulo`, `descripcion_articulo`, `numero_contrato` | * | *Obligatorios si la fila incluye artÃ­culo. Contrato + cÃ³digo identifican el artÃ­culo. |
| `fecha_adquisicion`, `proxima_fecha_pago` | | `dd/mm/aaaa`. |
| `precio_original`, `prima`, `valor_cuota`, `saldo_pendiente` | | Si se omite el saldo: cuotas pendientes Ã— valor de cuota. |
| `cantidad_cuotas`, `cuotas_pagadas` | | Enteros; pagadas â‰¤ total. |
| `estado_articulo` | | `vigente` / `cancelado` / `anulado` / `en_cobranza`. |

Reglas de duplicados: un cliente existente se actualiza solo si **cÃ³digo y DUI** coinciden; si el cÃ³digo pertenece a otro
DUI (o viceversa) la fila se marca con error. Filas repetidas en el archivo (mismo cliente + contrato + artÃ­culo) se
reportan como duplicadas. Las filas con errores no se importan. MÃ¡s de 300 filas vÃ¡lidas â†’ se procesa en cola.

---

## Plantillas y variables

`{{nombre}}` `{{primer_nombre}}` `{{dui}}` `{{dui_parcial}}` `{{codigo_cliente}}` `{{telefono}}` `{{estado_mora}}`
`{{dias_mora}}` `{{saldo_pendiente}}` `{{valor_cuota}}` `{{cuotas_pendientes}}` `{{fecha_pago}}` `{{cantidad_articulos}}`
`{{lista_articulos}}` `{{articulo_descripcion}}` `{{articulo_codigo}}` `{{articulo_contrato}}` `{{articulo_saldo}}`
`{{articulo_cuota}}` `{{articulo_cuotas_pendientes}}` `{{articulo_fecha_pago}}` `{{empresa}}` `{{telefono_empresa}}`
`{{correo_empresa}}` `{{direccion_empresa}}` `{{horario}}` `{{fecha_actual}}`

Los montos se insertan sin `$` (la plantilla decide: `${{saldo_pendiente}}`). Las variables de artÃ­culo usan el artÃ­culo
con saldo mÃ¡s prÃ³ximo a vencer. Las variables desconocidas se rechazan al guardar.

---

## CampaÃ±as: reglas de envÃ­o

- **Cobranza:** nunca a clientes solventes, en estados excluidos de campaÃ±as, sin saldo, inactivos, dados de baja o sin
  consentimiento de cobranza. OpciÃ³n de usar la **plantilla asociada a cada estado de mora**.
- **Promociones:** solo clientes con consentimiento explÃ­cito (`acepta_promociones`) y sin baja.
- **Anti-duplicados:** llave Ãºnica campaÃ±aâ€“cliente, clave de idempotencia por mensaje y separaciÃ³n mÃ­nima configurable
  (24 h por defecto) entre mensajes de cobranza al mismo cliente.
- **LÃ­mites del proveedor:** `RateLimiter` configurable (mensajes por minuto) y reintentos con espera creciente para
  errores temporales.
- **Horario:** las campaÃ±as de cobranza solo se envÃ­an en la ventana configurada (08:00â€“19:00 por defecto).
- Escribir `BAJA` / `STOP` (configurable) da de baja al cliente de mensajes masivos.

---

## IntegraciÃ³n con WhatsApp

```
app/Services/WhatsApp/
â”œâ”€â”€ Contracts/WhatsAppProvider.php    â† interfaz comÃºn (envÃ­o, plantillas, webhooks, firmas)
â”œâ”€â”€ Providers/SimulatorProvider.php   â† pruebas sin costo (por defecto)
â”œâ”€â”€ Providers/MetaCloudProvider.php   â† WhatsApp Cloud API (Graph API)
â”œâ”€â”€ Providers/TwilioProvider.php      â† Twilio WhatsApp
â””â”€â”€ WhatsAppManager.php               â† resuelve el proveedor activo
```

El resto del sistema (campaÃ±as, chatbot, bandeja) solo usa `MessageSender`, por lo que cambiar de proveedor no requiere
reconstruir nada.

**Simulador:** un % configurable de envÃ­os falla aleatoriamente y los nÃºmeros terminados en `0000` siempre fallan. Las
conversaciones creadas en el simulador **nunca** se envÃ­an por un proveedor real, aunque estÃ© activo.

### Conectar WhatsApp Cloud API (Meta)

1. En Meta for Developers crea una app de tipo *Business* con el producto WhatsApp.
2. Define en `.env` (o en ConfiguraciÃ³n â€º WhatsApp, donde se guardan **cifradas**):
   `WHATSAPP_META_PHONE_NUMBER_ID`, `WHATSAPP_META_ACCESS_TOKEN`, `WHATSAPP_META_APP_SECRET`, `WHATSAPP_META_VERIFY_TOKEN`.
3. Webhook: `https://TU-DOMINIO/api/webhooks/whatsapp/meta` con el *verify token* anterior; suscribe `messages`.
   (En local se puede exponer con `herd share` / Expose.)
4. Selecciona *WhatsApp Cloud API* como proveedor y usa **Probar conexiÃ³n**.
5. Fuera de la ventana de 24 h Meta exige **plantillas aprobadas**: en cada plantilla indica su nombre aprobado; las
   variables se envÃ­an como `{{1}}`, `{{2}}`â€¦ en el orden en que aparecen en el contenido.

Seguridad: se valida `X-Hub-Signature-256` con el App secret, los webhooks son idempotentes por `provider_message_id`
y los estados (`sent â†’ delivered â†’ read`, `failed`) solo avanzan.

### Conectar Twilio

`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_WHATSAPP_FROM` y webhook
`https://TU-DOMINIO/api/webhooks/whatsapp/twilio` ("When a message comes in"). Se valida `X-Twilio-Signature`.
Plantillas aprobadas: usar el *Content SID* (`HXâ€¦`) como nombre de plantilla.

---

## Chatbot

Basado en reglas (sin IA): intenciones con palabras clave administrables y prioridad. Reconoce: saldo, artÃ­culos,
cuotas pendientes, prÃ³xima fecha de pago, informaciÃ³n general, asesor, menÃº, saludo, agradecimiento y cierre de sesiÃ³n.

**VerificaciÃ³n de identidad** (nunca se muestran datos financieros sin ella): DUI + segundo factor configurable:

| MÃ©todo | Funcionamiento |
|---|---|
| `phone_match` (por defecto) | El mensaje debe venir del nÃºmero registrado del cliente. |
| `otp` | Se envÃ­a un cÃ³digo de 6 dÃ­gitos al nÃºmero registrado (vÃ¡lido 10 min). |
| `contract_number` | Ãšltimos 4 dÃ­gitos de cualquiera de sus contratos. |

Intentos fallidos limitados con bloqueo temporal; mensajes genÃ©ricos que no revelan si un DUI existe; la verificaciÃ³n
expira (30 min por defecto). Al pedir un asesor el bot se detiene, la conversaciÃ³n pasa a *Pendiente de asesor*, se
notifica al equipo (sin datos financieros) y aparece la alerta en el dashboard. El asesor puede devolverla al bot.

---

## Seguridad y buenas prÃ¡cticas implementadas

- Control de acceso por permisos en rutas (`permission:`) y en cada componente (`authorize`).
- CSRF en formularios; webhooks validados por firma y con limitaciÃ³n de tasa.
- Credenciales: variables de entorno con prioridad; si se guardan desde la UI, se cifran con `APP_KEY`.
- AuditorÃ­a (`activity_logs`) de inicios de sesiÃ³n, cambios de clientes, estados, importaciones, campaÃ±as, usuarios y configuraciÃ³n.
- Historial de mora inmutable (el modelo impide modificar o eliminar registros).
- `Model::preventLazyLoading()` fuera de producciÃ³n para detectar consultas N+1; paginaciÃ³n en todos los listados.
- Usuarios inactivos bloqueados aunque tengan sesiÃ³n abierta; lÃ­mite de intentos de inicio de sesiÃ³n.
- Transacciones en pagos, cambios de estado, importaciÃ³n por cliente y operaciones de conversaciÃ³n.

---

## Pruebas

```bash
php artisan test
```

58 pruebas (386 aserciones) organizadas por fase en `tests/Feature/Phase{1..5}*Test.php` y `tests/Unit/SupportTest.php`.
Usan SQLite en memoria (no tocan la base MySQL).

---

## Rendimiento

- **Iconos:** se renderizan con `<x-icon name="...">` (`App\Support\Icons`) desde `bootstrap/cache/lucide-icons.php`,
  generado por `php artisan icons:lucide` (se ejecuta automÃ¡ticamente en `composer install/dump-autoload`). Si agregas
  un Ã­cono nuevo en el cÃ³digo, vuelve a ejecutar el comando; mientras tanto se lee del disco como respaldo.
  Los componentes de Blade Icons estÃ¡n desactivados en `config/blade-icons.php`: registrarlos y resolverlos por
  peticiÃ³n costaba ~1 ms por Ã­cono y era la principal causa de lentitud (pÃ¡ginas de 300â€“450 ms â†’ 100â€“200 ms).
- Tras cambiar vistas en Windows: `php artisan view:cache`.

## SoluciÃ³n de problemas

- **CampaÃ±a "En proceso" que no avanza:** el worker de colas no estÃ¡ activo (`php artisan queue:work --queue=campaigns,whatsapp,default`). La pantalla de la campaÃ±a lo advierte.
- **CampaÃ±a programada que no inicia:** falta `php artisan schedule:work`.
- **Error `rename(... .tmp ...): Acceso denegado` en Windows:** carrera al compilar la misma vista en peticiones simultÃ¡neas. Ejecuta `php artisan view:cache` tras cada despliegue o cambio de vistas.
- **Cambios de configuraciÃ³n que no se reflejan:** `php artisan optimize:clear`.
