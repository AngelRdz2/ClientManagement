# CobraCRM — Clientes, cobranza y WhatsApp

Plataforma CRM para administrar clientes, artículos y saldos, controlar estados de mora configurables, enviar campañas
masivas de cobranza y promociones por WhatsApp, atender clientes con un chatbot basado en reglas y transferir
conversaciones a asesores humanos.

> **Modo simulación por defecto.** El sistema arranca con un proveedor de WhatsApp **simulado**: registra los envíos y
> permite probar todo el flujo sin enviar mensajes reales ni pagar por ellos. La interfaz lo indica en todo momento.
> Para conectar WhatsApp Cloud API (Meta) o Twilio solo se cambia el proveedor en **Configuración › WhatsApp**.

---

## Stack

| Componente | Versión |
|---|---|
| PHP | 8.4 |
| Laravel | **13.40** (Laravel 14 aún no está publicado; ver *Decisiones*) |
| Livewire (incluye Alpine.js) | 4.4 |
| Tailwind CSS | 4.x (Vite 7) |
| Laravel Excel (Maatwebsite) | 4.0 (PhpSpreadsheet 5) |
| Spatie Laravel Permission | 8.3 |
| Lucide Icons | `mallardduck/blade-lucide-icons` 2.0 |
| SweetAlert2 / Chart.js | 11 / 4 |
| Base de datos | MySQL 8 |
| Servidor local | Laravel Herd (Windows) |

---

## Instalación (Herd en Windows)

```bash
cd C:\Users\MiguelAmaya\PhpstormProjects\client-management
composer install
npm install
copy .env.example .env          # ajustar DB_USERNAME / DB_PASSWORD
php artisan key:generate
```

1. Crear la base de datos:
   `CREATE DATABASE client_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Migrar y cargar datos base + demostración:
   ```bash
   php artisan migrate --seed
   ```
   En producción: `php artisan migrate --force && php artisan db:seed --class=ProductionSeeder` (sin clientes demo).
3. Compilar el frontend: `npm run build` (o `npm run dev` mientras desarrollas).
4. Abrir **http://client-management.test** (Herd sirve automáticamente la carpeta `PhpstormProjects`).

### Procesos en segundo plano (necesarios para campañas e importaciones grandes)

Herd sirve la web, pero las colas y el programador de tareas se ejecutan aparte:

```bash
php artisan queue:work --queue=campaigns,whatsapp,default   # envíos de campañas, importaciones
php artisan schedule:work                                    # campañas programadas, recálculo de mora, limpieza
```

O todo junto (servidor, colas, scheduler, logs y Vite): `composer dev`.
En producción, usar Supervisor para `queue:work` y un cron `* * * * * php artisan schedule:run`.

### Usuarios de demostración

Contraseña de todos: `password` (cámbiala en producción).

| Correo | Rol |
|---|---|
| admin@crm.test | Administrador |
| supervisor@crm.test | Supervisor |
| asesor@crm.test · asesor2@crm.test | Asesor |
| cobranza@crm.test | Operador de cobranza |

El cliente demo **María Fernanda López Hernández** (DUI `01234567-8`, tel. `7012-3456`) reproduce el ejemplo del
chatbot: 2 artículos, saldo total $430.00.

---

## Módulos

| Módulo | Ruta | Descripción |
|---|---|---|
| Dashboard | `/` | KPIs de cartera, mora, mensajería y conversaciones; gráficos y alerta de clientes que esperan un asesor. |
| Clientes | `/clientes` | Búsqueda por nombre, DUI, teléfono o código; ficha con resumen financiero, artículos, pagos, historial de mora, mensajes y conversaciones. |
| Artículos y pagos | ficha del cliente | Alta/edición de artículos (contrato, cuotas, saldo, próxima fecha) y registro de pagos que actualizan saldo y cuotas. |
| Mensaje individual | ficha del cliente › *Enviar mensaje* | Mensaje de WhatsApp a un solo cliente (texto libre o plantilla rellenada con sus datos), con vista previa. Queda en su conversación; opcionalmente se asigna al usuario para atender la respuesta. Respeta consentimiento de promociones y la ventana de 24 h. |
| Estados de mora | `/cobranza/estados` | Estados administrables: días, color, prioridad, plantilla, envíos automáticos, exclusión de campañas, solvente/crítico. |
| Actualizar estados | `/cobranza/actualizar-estados` | Cambio manual individual o masivo con motivo, y recálculo automático por fechas de pago. |
| Historial de mora | `/cobranza/historial` | Registro inmutable: estado anterior/nuevo, fecha, usuario, origen y motivo. |
| Importar Excel | `/clientes/importar` | XLSX/CSV con plantilla descargable, previsualización, validación por fila, duplicados, reporte de errores y cola. |
| Plantillas | `/plantillas` | Plantillas por categoría con variables dinámicas y previsualización con un cliente real. |
| Campañas de cobranza | `/campanas` | Segmentación, plantilla por estado de mora, previsualización, programación y resultados por destinatario. |
| Promociones | `/promociones` | Módulo separado: solo clientes con consentimiento; plantillas promocionales. |
| Historial de mensajes | `/mensajes` | Todos los mensajes enviados/recibidos con estado de entrega y reintento de fallidos. |
| Conversaciones | `/conversaciones` | Bandeja estilo WhatsApp Web (3 columnas): tomar, asignar, notas internas, devolver al bot, cerrar. |
| Simulador WhatsApp | `/simulador` | Escribe como cliente y observa al bot; simula errores de envío. |
| Chatbot | `/chatbot` | Intenciones, palabras clave, respuestas y método de verificación de identidad. |
| Reportes | `/reportes` | 10 reportes con filtro de fechas y exportación a Excel. |
| Usuarios y roles | `/usuarios` | Usuarios, roles y matriz de permisos editable. |
| Configuración | `/configuracion` | Empresa, WhatsApp y credenciales, horario, notificaciones, comunicación y cobranza. |

### Roles y permisos iniciales

| Permiso | Admin | Supervisor | Asesor | Operador de cobranza |
|---|:-:|:-:|:-:|:-:|
| Consultar clientes | ✓ | ✓ | ✓ | ✓ |
| Crear y editar clientes | ✓ | ✓ | | ✓ |
| Importar Excel | ✓ | ✓ | | ✓ |
| Administrar estados de mora | ✓ | ✓ | | |
| Crear campañas | ✓ | ✓ | | ✓ |
| Enviar mensajes | ✓ | ✓ | ✓ | ✓ |
| Atender conversaciones | ✓ | ✓ | ✓ | |
| Administrar plantillas | ✓ | ✓ | | ✓ |
| Consultar reportes | ✓ | ✓ | | ✓ |
| Administrar usuarios / configuración | ✓ | | | |

Se modifican desde **Usuarios y roles › Roles y permisos**.

---

## Estructura del Excel de importación

Una fila por artículo/contrato. Si un cliente tiene varios artículos, se repiten sus datos (mismo código y DUI).
Descarga la plantilla desde la pantalla de importación (incluye hojas *Instrucciones* y *Estados de mora*).

| Columna | Oblig. | Descripción |
|---|:-:|---|
| `codigo_cliente` | ✓ | Identifica al cliente para crear o actualizar. |
| `nombre_completo` | ✓ | |
| `dui` | ✓ | 9 dígitos (`00000000-0`), se valida el dígito verificador. |
| `telefono_principal` | ✓ | Número con WhatsApp (8 dígitos o con código de país). |
| `telefono_secundario`, `correo`, `direccion`, `observaciones` | | |
| `fecha_registro` | | `dd/mm/aaaa` (por defecto, la fecha de importación). |
| `estado_cliente` | | `activo` / `inactivo`. |
| `estado_mora` | | Código o nombre del estado (ej. `MORA_30`). |
| `dias_mora` | | Si no hay `estado_mora`, el estado se calcula con estos días. |
| `acepta_promociones` | | `si` / `no` (registra fecha y origen del consentimiento). |
| `codigo_articulo`, `descripcion_articulo`, `numero_contrato` | * | *Obligatorios si la fila incluye artículo. Contrato + código identifican el artículo. |
| `fecha_adquisicion`, `proxima_fecha_pago` | | `dd/mm/aaaa`. |
| `precio_original`, `prima`, `valor_cuota`, `saldo_pendiente` | | Si se omite el saldo: cuotas pendientes × valor de cuota. |
| `cantidad_cuotas`, `cuotas_pagadas` | | Enteros; pagadas ≤ total. |
| `estado_articulo` | | `vigente` / `cancelado` / `anulado` / `en_cobranza`. |

Reglas de duplicados: un cliente existente se actualiza solo si **código y DUI** coinciden; si el código pertenece a otro
DUI (o viceversa) la fila se marca con error. Filas repetidas en el archivo (mismo cliente + contrato + artículo) se
reportan como duplicadas. Las filas con errores no se importan. Más de 300 filas válidas → se procesa en cola.

---

## Plantillas y variables

`{{nombre}}` `{{primer_nombre}}` `{{dui}}` `{{dui_parcial}}` `{{codigo_cliente}}` `{{telefono}}` `{{estado_mora}}`
`{{dias_mora}}` `{{saldo_pendiente}}` `{{valor_cuota}}` `{{cuotas_pendientes}}` `{{fecha_pago}}` `{{cantidad_articulos}}`
`{{lista_articulos}}` `{{articulo_descripcion}}` `{{articulo_codigo}}` `{{articulo_contrato}}` `{{articulo_saldo}}`
`{{articulo_cuota}}` `{{articulo_cuotas_pendientes}}` `{{articulo_fecha_pago}}` `{{empresa}}` `{{telefono_empresa}}`
`{{correo_empresa}}` `{{direccion_empresa}}` `{{horario}}` `{{fecha_actual}}`

Los montos se insertan sin `$` (la plantilla decide: `${{saldo_pendiente}}`). Las variables de artículo usan el artículo
con saldo más próximo a vencer. Las variables desconocidas se rechazan al guardar.

---

## Campañas: reglas de envío

- **Cobranza:** nunca a clientes solventes, en estados excluidos de campañas, sin saldo, inactivos, dados de baja o sin
  consentimiento de cobranza. Opción de usar la **plantilla asociada a cada estado de mora**.
- **Promociones:** solo clientes con consentimiento explícito (`acepta_promociones`) y sin baja.
- **Anti-duplicados:** llave única campaña–cliente, clave de idempotencia por mensaje y separación mínima configurable
  (24 h por defecto) entre mensajes de cobranza al mismo cliente.
- **Límites del proveedor:** `RateLimiter` configurable (mensajes por minuto) y reintentos con espera creciente para
  errores temporales.
- **Horario:** las campañas de cobranza solo se envían en la ventana configurada (08:00–19:00 por defecto).
- Escribir `BAJA` / `STOP` (configurable) da de baja al cliente de mensajes masivos.

---

## Integración con WhatsApp

```
app/Services/WhatsApp/
├── Contracts/WhatsAppProvider.php    ← interfaz común (envío, plantillas, webhooks, firmas)
├── Providers/SimulatorProvider.php   ← pruebas sin costo (por defecto)
├── Providers/MetaCloudProvider.php   ← WhatsApp Cloud API (Graph API)
├── Providers/TwilioProvider.php      ← Twilio WhatsApp
└── WhatsAppManager.php               ← resuelve el proveedor activo
```

El resto del sistema (campañas, chatbot, bandeja) solo usa `MessageSender`, por lo que cambiar de proveedor no requiere
reconstruir nada.

**Simulador:** un % configurable de envíos falla aleatoriamente y los números terminados en `0000` siempre fallan. Las
conversaciones creadas en el simulador **nunca** se envían por un proveedor real, aunque esté activo.

### Conectar WhatsApp Cloud API (Meta)

1. En Meta for Developers crea una app de tipo *Business* con el producto WhatsApp.
2. Define en `.env` (o en Configuración › WhatsApp, donde se guardan **cifradas**):
   `WHATSAPP_META_PHONE_NUMBER_ID`, `WHATSAPP_META_ACCESS_TOKEN`, `WHATSAPP_META_APP_SECRET`, `WHATSAPP_META_VERIFY_TOKEN`.
3. Webhook: `https://TU-DOMINIO/api/webhooks/whatsapp/meta` con el *verify token* anterior; suscribe `messages`.
   (En local se puede exponer con `herd share` / Expose.)
4. Selecciona *WhatsApp Cloud API* como proveedor y usa **Probar conexión**.
5. Fuera de la ventana de 24 h Meta exige **plantillas aprobadas**: en cada plantilla indica su nombre aprobado; las
   variables se envían como `{{1}}`, `{{2}}`… en el orden en que aparecen en el contenido.

Seguridad: se valida `X-Hub-Signature-256` con el App secret, los webhooks son idempotentes por `provider_message_id`
y los estados (`sent → delivered → read`, `failed`) solo avanzan.

### Conectar Twilio

1. Credenciales: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_WHATSAPP_FROM` (o desde Configuración › WhatsApp).
2. **Recibir respuestas de clientes y estados de entrega** requiere una URL pública HTTPS:
   - Producción: `WHATSAPP_PUBLIC_URL=https://tu-dominio`.
   - Local: abre un túnel (`herd share` en la carpeta del proyecto, o ngrok / cloudflared), pon su URL en
     `WHATSAPP_PUBLIC_URL` y ejecuta `php artisan config:clear`. La URL del túnel cambia cada vez que se abre:
     actualiza ambos lados.
3. En Twilio (Sandbox: Messaging › Try it out › Send a WhatsApp message › *Sandbox settings*), en
   **"When a message comes in"** pega la URL que muestra Configuración › WhatsApp
   (`https://…/api/webhooks/whatsapp/twilio`, método POST).
4. La firma `X-Twilio-Signature` se valida contra la URL pública. Los *StatusCallback* (entregado/leído) se envían solo
   cuando la URL es pública; sin ella los mensajes quedan "En cola" aunque sí se entreguen.
5. Plantillas aprobadas: pega el *Content SID* (`HX…`) en Plantillas › Editar › sección **Twilio** (campo propio,
   independiente del nombre de Meta).

---

## Chatbot

Basado en reglas (sin IA): intenciones con palabras clave administrables y prioridad. Reconoce: saldo, artículos,
cuotas pendientes, próxima fecha de pago, información general, asesor, menú, saludo, agradecimiento y cierre de sesión.

**Verificación de identidad** (nunca se muestran datos financieros sin ella): DUI + segundo factor configurable:

| Método | Funcionamiento |
|---|---|
| `phone_match` (por defecto) | El mensaje debe venir del número registrado del cliente. |
| `otp` | Se envía un código de 6 dígitos al número registrado (válido 10 min). |
| `contract_number` | Últimos 4 dígitos de cualquiera de sus contratos. |

Intentos fallidos limitados con bloqueo temporal; mensajes genéricos que no revelan si un DUI existe; la verificación
expira (30 min por defecto). Al pedir un asesor el bot se detiene, la conversación pasa a *Pendiente de asesor*, se
notifica al equipo (sin datos financieros) y aparece la alerta en el dashboard. El asesor puede devolverla al bot.

---

## Seguridad y buenas prácticas implementadas

- Control de acceso por permisos en rutas (`permission:`) y en cada componente (`authorize`).
- CSRF en formularios; webhooks validados por firma y con limitación de tasa.
- Credenciales: variables de entorno con prioridad; si se guardan desde la UI, se cifran con `APP_KEY`.
- Auditoría (`activity_logs`) de inicios de sesión, cambios de clientes, estados, importaciones, campañas, usuarios y configuración.
- Historial de mora inmutable (el modelo impide modificar o eliminar registros).
- `Model::preventLazyLoading()` fuera de producción para detectar consultas N+1; paginación en todos los listados.
- Usuarios inactivos bloqueados aunque tengan sesión abierta; límite de intentos de inicio de sesión.
- Transacciones en pagos, cambios de estado, importación por cliente y operaciones de conversación.

---

## Pruebas

```bash
php artisan test
```

62 pruebas (410 aserciones) organizadas por fase en `tests/Feature/Phase{1..5}*Test.php` y `tests/Unit/SupportTest.php`.
Usan SQLite en memoria (no tocan la base MySQL).

---

## Rendimiento

- **Iconos:** se renderizan con `<x-icon name="...">` (`App\Support\Icons`) desde `bootstrap/cache/lucide-icons.php`,
  generado por `php artisan icons:lucide` (se ejecuta automáticamente en `composer install/dump-autoload`). Si agregas
  un ícono nuevo en el código, vuelve a ejecutar el comando; mientras tanto se lee del disco como respaldo.
  Los componentes de Blade Icons están desactivados en `config/blade-icons.php`: registrarlos y resolverlos por
  petición costaba ~1 ms por ícono y era la principal causa de lentitud (páginas de 300–450 ms → 100–200 ms).
- Tras cambiar vistas en Windows: `php artisan view:cache`.

## Solución de problemas

- **Campaña "En proceso" que no avanza:** el worker de colas no está activo (`php artisan queue:work --queue=campaigns,whatsapp,default`). La pantalla de la campaña lo advierte.
- **Campaña programada que no inicia:** falta `php artisan schedule:work`.
- **Error `rename(... .tmp ...): Acceso denegado` en Windows:** carrera al compilar la misma vista en peticiones simultáneas. Ejecuta `php artisan view:cache` tras cada despliegue o cambio de vistas.
- **Cambios de configuración que no se reflejan:** `php artisan optimize:clear`.
