# PROGRESS — CobraCRM

Registro de fases del proyecto.

## Fase 1: Fundación — ✅ Completada

- [x] Verificación del entorno: PHP 8.4.15, Composer 2.9.5, Herd 1.24, MySQL 8.0.44, Node 25.
- [x] Proyecto creado con **Laravel 13.40** (Laravel 14 no está publicado; decisión confirmada por el usuario).
- [x] Dominio Herd `http://client-management.test`; base MySQL `client_management`; `.env` (es, America/El_Salvador).
- [x] Paquetes: Livewire 4.4, Laravel Excel 4.0, Spatie Permission 8.3, Blade Lucide Icons 2.0, Tailwind 4, SweetAlert2, Chart.js.
- [x] Esquema completo de BD (sección 18) con índices y llaves foráneas; modelos, enums y relaciones.
- [x] Layout SaaS (sidebar colapsable/responsive, buscador global, notificaciones, perfil), login y dashboard.
- [x] Seeders: roles/permisos, usuarios, plantillas, estados de mora, flujos del chatbot, 150 clientes demo.

## Fase 2: Clientes — ✅ Completada

- [x] CRUD de clientes con validación de DUI (dígito verificador) y teléfono; desactivación/reactivación.
- [x] Ficha del cliente: resumen financiero, artículos, pagos, historial de mora, mensajes y conversaciones.
- [x] Gestión de artículos (1:N) y registro de pagos (saldo, cuotas y próxima fecha se actualizan).
- [x] Estados de mora administrables (sin estados en código) con banderas y plantilla asociada.
- [x] Actualización manual (individual y masiva) y recálculo automático (job + comando programado).
- [x] Historial de estados inmutable.
- [x] Importación Excel/CSV: plantilla, previsualización, validación por fila, duplicados, reporte de errores, cola.
- [x] Grupos de clientes.

## Fase 3: Mensajería — ✅ Completada

- [x] Plantillas con variables dinámicas (cliente, cuenta, artículo, empresa) y previsualización con cliente real.
- [x] Campañas de cobranza con plantilla por estado de mora; campañas promocionales en módulo separado con consentimiento.
- [x] Segmentación (individuales, grupos, estados, solventes, saldo, días, fecha de registro) y previsualización.
- [x] Envíos en colas con límite de tasa, reintentos, idempotencia y anti-duplicados; programación de campañas.
- [x] Proveedor simulado; historial de mensajes con reintento de fallidos.

## Fase 4: Chatbot — ✅ Completada

- [x] Bandeja de conversaciones de 3 columnas con filtros, asignación, notas internas, devolver al bot y cerrar.
- [x] Chatbot por reglas (intenciones administrables): saldo, artículos, cuotas, fecha de pago, información, asesor.
- [x] Verificación de identidad: DUI + segundo factor configurable (número registrado / OTP / contrato), bloqueo por intentos.
- [x] Transferencia a asesor con notificaciones y alerta en dashboard; registro del asesor que atendió.
- [x] Simulador de WhatsApp; baja por palabra clave.

## Fase 5: Reportes e integraciones — ✅ Completada

- [x] 10 reportes con filtros de fecha y exportación a Excel.
- [x] Webhooks Meta (verificación + firma HMAC) y Twilio (firma), mensajes entrantes y estados de entrega.
- [x] Proveedores WhatsApp Cloud API y Twilio (envío de texto y plantillas aprobadas, ventana de 24 h).
- [x] Configuración general (empresa, WhatsApp, credenciales cifradas, horario, notificaciones, comunicación, cobranza).
- [x] Usuarios, roles y matriz de permisos editable.

## Mejoras posteriores

- [x] **Rendimiento:** diagnóstico con perfilado dentro de Herd (arranque 10–15 ms, SQL 25–55 ms, renderizado
      300–500 ms). Causa: componentes de iconos (`x-dynamic-component` / Blade Icons, ~1 ms por ícono).
      Solución: componente `<x-icon>` con SVG precompilados. Resultado: páginas de 300–450 ms → 100–200 ms.
- [x] **Mensaje individual desde la ficha del cliente** (no masivo), con plantillas, vista previa, asignación de la
      conversación, anti-doble envío y reglas de consentimiento / ventana de 24 h. 6 pruebas nuevas.

## Verificación

- `php artisan test`: **62 pruebas, 410 aserciones — todas pasan.**
- Verificación en navegador (Chrome): login, dashboard, clientes, ficha, simulador (conversación del ejemplo con saldo
  real $430.00), creación y envío de una campaña de cobranza (48 destinatarios procesados por la cola), reportes;
  todas las pantallas responden 200 sin errores de JavaScript.

## Pendiente / siguientes pasos sugeridos

- [ ] Conectar credenciales reales de Meta o Twilio y registrar el webhook con un dominio público (HTTPS).
- [ ] Aprobar en Meta las plantillas de cobranza/promoción y registrar sus nombres en cada plantilla.
- [ ] Configurar Supervisor (colas) y cron (scheduler) en el servidor de producción.
- [ ] Cambiar las contraseñas de los usuarios de demostración.

## Decisiones técnicas

| Decisión | Motivo |
|---|---|
| Laravel 13 en lugar de 14 | Laravel 14 no existe como versión estable (solo `dev-master`). Livewire, Excel y Spatie están verificados con 13. |
| Estados de mora en BD con banderas `is_solvent`, `is_critical`, `is_auto_assignable` | El sistema razona sobre el estado sin depender de su nombre; los estados manuales no se sobrescriben al recalcular. |
| Totales desnormalizados en `customers` (saldo, cuotas, próxima fecha) | Filtros por rango de saldo y reportes rápidos; se recalculan al guardar artículos. |
| `messages` (registro del proveedor) + `conversation_messages` (línea de tiempo) | La línea de tiempo incluye eventos y notas internas que no son mensajes de WhatsApp. |
| Respuestas del bot/asesor se envían en la misma petición; campañas por cola | Interacción inmediata en conversaciones; volumen controlado y con límite de tasa en campañas. |
| Destinatarios calculados al iniciar la campaña | Usa los datos vigentes al momento del envío, no los de la creación del borrador. |
| Plantillas de Meta: variables en orden de aparición → `{{1}}`, `{{2}}` | Una sola plantilla interna sirve para el simulador y para la plantilla aprobada. |
| Credenciales: variables de entorno primero, luego valor cifrado en BD | Nunca se almacenan tokens en código. |
| `Model::preventLazyLoading()` fuera de producción | Detecta consultas N+1 durante el desarrollo. |
| Vistas compiladas de pruebas en carpeta propia | Evita carreras de compilación en Windows entre el navegador y la suite de pruebas. |
