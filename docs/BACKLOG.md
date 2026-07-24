# Backlog

Lista priorizada de trabajo. Claude Code toma de arriba hacia abajo cuando no hay nada roto ni riesgos de seguridad pendientes. Puede agregar tareas al final (con diagnóstico), pero no reordenar la prioridad de las que puso el humano.

## Contexto de producto (leer antes de tocar el backlog)

Pilo es un empleado digital para negocios pequeños, al que se le habla por WhatsApp. Además del Recepcionista ya construido, la visión completa tiene 4 productos:

- **P1 — Recepcionista** (existente): atiende el WhatsApp del negocio con el número del negocio.
- **P2 — Cotizador**: el dueño carga contexto comercial (catálogo, precios, condiciones) y Pilo genera cotizaciones. Panel propio, sin WhatsApp. Regla dura: nunca inventar precios.
- **P3 — Copiloto financiero**: notas de voz/texto al número de Pilo → registro de ingresos/gastos → reporte mensual. Solo chat. Es registro y reporte, nunca asesoría contable/tributaria.
- **P4 — Cobrador**: gestiona cobro de facturas pendientes. **Regla de oro innegociable:** los mensajes de cobro a deudores SIEMPRE salen desde el número del propio negocio (vía P1) o por correo — NUNCA desde el número de Pilo. Restricciones legales (horario hábil, tope de frecuencia, STOP list, prohibición de contactar terceros) van como validaciones de código, no como instrucciones al prompt.

Todos cuelgan de un modelo de cuentas/suscripciones común y de un número de WhatsApp propio de Pilo (el "hub") que hace de vitrina y consola. Detalle completo de arquitectura en `docs/ARQUITECTURA-PLATAFORMA.md`.

**Orden de construcción:** cimientos multi-producto → hub conversacional → Cotizador → Copiloto → Cobrador. No saltar el orden: cada etapa depende de la anterior.

## Prioridad alta — Etapa A: cimientos multi-producto

- [ ] (alta) Modelo de datos: `accounts`, `account_users` (con `wa_id`), `products` (catálogo P1-P4), `subscriptions` (cuenta+producto+estado+periodo — ESTA tabla es la whitelist, no crear una lista aparte), `payments`. Migraciones aditivas, factories, seeders de demo.
- [ ] (alta) Migrar el `business` del Recepcionista actual para que cuelgue de `account`, sin romper nada de lo existente. Criterio de terminado: Recepcionista funciona igual + tests en verde + `migrate:fresh --seed` limpio.
- [ ] (alta) Contadores de consumo desde el día uno: tokens y costo estimado de Claude por mensaje y por cuenta, guardados y consultables.
- [ ] (media) Panel super admin: vista por producto con suscriptores, estado, pagos del mes y costo de API vs. lo cobrado.

## Prioridad alta — seguridad y calidad base (transversal, siempre vigente)

- [ ] (alta) Auditar aislamiento multi-tenant en todo lo nuevo y existente: ninguna cuenta puede leer datos de otra, por ninguna ruta. Tests que lo prueben.
- [ ] (alta) Cobertura de tests del agente del Recepcionista con API mockeada.
- [ ] (alta) Robustez del webhook: firma, idempotencia, jobs encolados.

## Prioridad media — Etapa B: hub conversacional de Pilo

- [ ] (media) Segundo número tipo `plataforma` en el sistema; ruteo del webhook por `phone_number_id` hacia el agente correspondiente (hub vs. negocio) sin romper el ruteo actual.
- [ ] (media) `PiloHubAgent`: identifica al remitente por `wa_id`, resuelve cuenta y suscripciones activas, ofrece solo lo contratado.
- [ ] (media) Modo vitrina para desconocidos: explica productos, responde precios, captura interés como lead, notifica al admin. Nunca ejecuta acciones de producto sin suscripción activa.
- [ ] (media) Menús interactivos + comandos universales "menu" / "ayuda".

## Prioridad media — Etapa C: Cotizador (P2)

- [ ] (media) Panel: carga de contexto comercial (catálogo, precios, mano de obra, márgenes, condiciones) por cuenta.
- [ ] (media) Generación de cotización desde descripción en lenguaje natural, con edición manual de ítems antes de emitir. Regla dura: si falta un precio, preguntar o dejar el ítem marcado — nunca inventarlo.
- [ ] (media) PDF con la marca del NEGOCIO (no de Pilo), envío por correo/WhatsApp, historial y estados (borrador/enviada/aceptada/rechazada).

## Prioridad media-baja — Etapa D: Copiloto financiero (P3)

- [ ] (media) Transcripción de notas de voz (proveedor STT configurable — Claude no procesa audio) + extracción estructurada de gasto/ingreso.
- [ ] (media) Confirmación por chat antes de guardar cualquier dato extraído. Corrección del último registro. Consultas por chat del acumulado del mes.
- [ ] (media) Cierre de mes: reporte (PDF + resumen por chat) con aclaración explícita de que no es contabilidad ni asesoría tributaria.

## Prioridad baja — Etapa E: Cobrador (P4)

- [ ] (baja) Registro de facturas pendientes: texto, imagen (extracción con confirmación obligatoria del dueño antes de guardar), carga masiva.
- [ ] (baja) Motor de envío que RESPETA LA REGLA DE ORO: canal = número del negocio (requiere P1 activo) o correo o mensaje listo para que el dueño lo mande. Jamás desde el número de Pilo.
- [ ] (baja) Todas las restricciones legales como validaciones de código: horario hábil configurable, tope de frecuencia por deudor, STOP list, prohibición de contactar terceros, auditoría completa de cada envío.

## Deuda técnica y pulido (cuando no haya nada de lo anterior pendiente ni roto)

- [ ] (baja) `.env.example` completo, README actualizado con el estado real del sistema.
- [ ] (baja) Microcopy y estados vacíos según `docs/BRAND.md`.

## Agregadas por Claude Code

<!-- Claude Code agrega aquí lo que encuentre, con diagnóstico y criterio de terminado -->
