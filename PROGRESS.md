# PROGRESS â€” CobraCRM

Registro de fases del proyecto.

## Fase 1: FundaciÃ³n â€” âœ… Completada

- [x] VerificaciÃ³n del entorno: PHP 8.4.15, Composer 2.9.5, Herd 1.24, MySQL 8.0.44, Node 25.
- [x] Proyecto creado con **Laravel 13.40** (Laravel 14 no estÃ¡ publicado; decisiÃ³n confirmada por el usuario).
- [x] Dominio Herd `http://client-management.test`; base MySQL `client_management`; `.env` (es, America/El_Salvador).
- [x] Paquetes: Livewire 4.4, Laravel Excel 4.0, Spatie Permission 8.3, Blade Lucide Icons 2.0, Tailwind 4, SweetAlert2, Chart.js.
- [x] Esquema completo de BD (secciÃ³n 18) con Ã­ndices y llaves forÃ¡neas; modelos, enums y relaciones.
- [x] Layout SaaS (sidebar colapsable/responsive, buscador global, notificaciones, perfil), login y dashboard.
- [x] Seeders: roles/permisos, usuarios, plantillas, estados de mora, flujos del chatbot, 150 clientes demo.

## Fase 2: Clientes â€” âœ… Completada

- [x] CRUD de clientes con validaciÃ³n de DUI (dÃ­gito verificador) y telÃ©fono; desactivaciÃ³n/reactivaciÃ³n.
- [x] Ficha del cliente: resumen financiero, artÃ­culos, pagos, historial de mora, mensajes y conversaciones.
- [x] GestiÃ³n de artÃ­culos (1:N) y registro de pagos (saldo, cuotas y prÃ³xima fecha se actualizan).
- [x] Estados de mora administrables (sin estados en cÃ³digo) con banderas y plantilla asociada.
- [x] ActualizaciÃ³n manual (individual y masiva) y recÃ¡lculo automÃ¡tico (job + comando programado).
- [x] Historial de estados inmutable.
- [x] ImportaciÃ³n Excel/CSV: plantilla, previsualizaciÃ³n, validaciÃ³n por fila, duplicados, reporte de errores, cola.
- [x] Grupos de clientes.

## Fase 3: MensajerÃ­a â€” âœ… Completada

- [x] Plantillas con variables dinÃ¡micas (cliente, cuenta, artÃ­culo, empresa) y previsualizaciÃ³n con cliente real.
- [x] CampaÃ±as de cobranza con plantilla por estado de mora; campaÃ±as promocionales en mÃ³dulo separado con consentimiento.
- [x] SegmentaciÃ³n (individuales, grupos, estados, solventes, saldo, dÃ­as, fecha de registro) y previsualizaciÃ³n.
- [x] EnvÃ­os en colas con lÃ­mite de tasa, reintentos, idempotencia y anti-duplicados; programaciÃ³n de campaÃ±as.
- [x] Proveedor simulado; historial de mensajes con reintento de fallidos.

## Fase 4: Chatbot â€” âœ… Completada

- [x] Bandeja de conversaciones de 3 columnas con filtros, asignaciÃ³n, notas internas, devolver al bot y cerrar.
- [x] Chatbot por reglas (intenciones administrables): saldo, artÃ­culos, cuotas, fecha de pago, informaciÃ³n, asesor.
- [x] VerificaciÃ³n de identidad: DUI + segundo factor configurable (nÃºmero registrado / OTP / contrato), bloqueo por intentos.
- [x] Transferencia a asesor con notificaciones y alerta en dashboard; registro del asesor que atendiÃ³.
- [x] Simulador de WhatsApp; baja por palabra clave.

## Fase 5: Reportes e integraciones â€” âœ… Completada

- [x] 10 reportes con filtros de fecha y exportaciÃ³n a Excel.
- [x] Webhooks Meta (verificaciÃ³n + firma HMAC) y Twilio (firma), mensajes entrantes y estados de entrega.
- [x] Proveedores WhatsApp Cloud API y Twilio (envÃ­o de texto y plantillas aprobadas, ventana de 24 h).
- [x] ConfiguraciÃ³n general (empresa, WhatsApp, credenciales cifradas, horario, notificaciones, comunicaciÃ³n, cobranza).
- [x] Usuarios, roles y matriz de permisos editable.

## Mejoras posteriores

- [x] **Rendimiento:** diagnÃ³stico con perfilado dentro de Herd (arranque 10â€“15 ms, SQL 25â€“55 ms, renderizado
      300â€“500 ms). Causa: componentes de iconos (`x-dynamic-component` / Blade Icons, ~1 ms por Ã­cono).
      SoluciÃ³n: componente `<x-icon>` con SVG precompilados. Resultado: pÃ¡ginas de 300â€“450 ms â†’ 100â€“200 ms.
- [x] **Mensaje individual desde la ficha del cliente** (no masivo), con plantillas, vista previa, asignaciÃ³n de la
      conversaciÃ³n, anti-doble envÃ­o y reglas de consentimiento / ventana de 24 h. 6 pruebas nuevas.

## VerificaciÃ³n

- `php artisan test`: **58 pruebas, 386 aserciones â€” todas pasan.**
- VerificaciÃ³n en navegador (Chrome): login, dashboard, clientes, ficha, simulador (conversaciÃ³n del ejemplo con saldo
  real $430.00), creaciÃ³n y envÃ­o de una campaÃ±a de cobranza (48 destinatarios procesados por la cola), reportes;
  todas las pantallas responden 200 sin errores de JavaScript.

## Pendiente / siguientes pasos sugeridos

- [ ] Conectar credenciales reales de Meta o Twilio y registrar el webhook con un dominio pÃºblico (HTTPS).
- [ ] Aprobar en Meta las plantillas de cobranza/promociÃ³n y registrar sus nombres en cada plantilla.
- [ ] Configurar Supervisor (colas) y cron (scheduler) en el servidor de producciÃ³n.
- [ ] Cambiar las contraseÃ±as de los usuarios de demostraciÃ³n.

## Decisiones tÃ©cnicas

| DecisiÃ³n | Motivo |
|---|---|
| Laravel 13 en lugar de 14 | Laravel 14 no existe como versiÃ³n estable (solo `dev-master`). Livewire, Excel y Spatie estÃ¡n verificados con 13. |
| Estados de mora en BD con banderas `is_solvent`, `is_critical`, `is_auto_assignable` | El sistema razona sobre el estado sin depender de su nombre; los estados manuales no se sobrescriben al recalcular. |
| Totales desnormalizados en `customers` (saldo, cuotas, prÃ³xima fecha) | Filtros por rango de saldo y reportes rÃ¡pidos; se recalculan al guardar artÃ­culos. |
| `messages` (registro del proveedor) + `conversation_messages` (lÃ­nea de tiempo) | La lÃ­nea de tiempo incluye eventos y notas internas que no son mensajes de WhatsApp. |
| Respuestas del bot/asesor se envÃ­an en la misma peticiÃ³n; campaÃ±as por cola | InteracciÃ³n inmediata en conversaciones; volumen controlado y con lÃ­mite de tasa en campaÃ±as. |
| Destinatarios calculados al iniciar la campaÃ±a | Usa los datos vigentes al momento del envÃ­o, no los de la creaciÃ³n del borrador. |
| Plantillas de Meta: variables en orden de apariciÃ³n â†’ `{{1}}`, `{{2}}` | Una sola plantilla interna sirve para el simulador y para la plantilla aprobada. |
| Credenciales: variables de entorno primero, luego valor cifrado en BD | Nunca se almacenan tokens en cÃ³digo. |
| `Model::preventLazyLoading()` fuera de producciÃ³n | Detecta consultas N+1 durante el desarrollo. |
| Vistas compiladas de pruebas en carpeta propia | Evita carreras de compilaciÃ³n en Windows entre el navegador y la suite de pruebas. |
