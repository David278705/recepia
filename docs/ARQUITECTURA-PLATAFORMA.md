# PILO — Plataforma multi-producto. Visión, arquitectura y plan de implementación

> Cómo usarlo: guarda como `PILO-PLATAFORMA.md` en la raíz del repo. Este documento NO se ejecuta de una sola vez. Dile a Claude Code: "Lee PILO-PLATAFORMA.md, ejecuta solo la ETAPA A y espera mi OK". Cada etapa deja el sistema funcionando y desplegable.

---

## 1. QUÉ ES PILO (visión refinada)

Pilo es **un empleado digital para negocios pequeños de LatAm, al que se le habla por WhatsApp.**

No es un panel que el dueño debe aprender a usar: es un número de WhatsApp con el que conversa como conversaría con un empleado. Detrás, Pilo ofrece varios "oficios" (productos) que el dueño contrata por separado. Algunos oficios viven enteramente dentro del chat; otros tienen además un panel web cuando la información es demasiado densa para un chat.

**El número de Pilo** (una sola WABA nuestra, la misma para todos los clientes) cumple tres funciones:

1. **Vitrina y soporte:** cualquiera le escribe, pregunta qué hace Pilo, cuánto cuesta, y recibe respuesta. Es nuestro canal de captación.
2. **Consola de trabajo:** los clientes con productos "conversacionales" contratados operan desde ahí, por menús y lenguaje natural.
3. **Identidad de marca:** Pilo es el mismo personaje siempre, independiente del producto.

**Principio de identidad (crítico, no negociar):** cuando Pilo habla con el DUEÑO del negocio, es Pilo. Cuando Pilo habla con los CLIENTES del negocio (producto Recepcionista, y mensajes de cobro), habla como asistente de ese negocio, desde el número de ese negocio. La marca Pilo es para quien paga; el cliente final le pertenece al negocio.

## 2. LOS CUATRO PRODUCTOS

### P1 — Recepcionista (ya construido)

Atiende el WhatsApp del negocio con el número del negocio (coexistencia). Responde FAQs, agenda citas, escala al dueño. **Canal:** número del cliente. **Interfaz:** panel web + WhatsApp. **Estado:** existente, se integra al nuevo modelo de cuentas.

### P2 — Cotizador

El dueño carga su contexto (catálogo, precios, materiales, mano de obra, márgenes, condiciones comerciales) y Pilo genera cotizaciones profesionales para sus clientes: el dueño describe el trabajo, Pilo produce el documento con ítems, cantidades, precios y totales, listo para descargar en PDF o enviar por WhatsApp/correo. **Canal:** web (no requiere WhatsApp). **Interfaz:** panel propio. **Por qué va primero:** es el más simple (sin tiempo real, sin webhooks, sin coexistencia) y el de valor más evidente para negocios de pintura, remodelación, talleres y servicios — justo los clientes freelance que ya existen.

### P3 — Copiloto financiero

El dueño le manda notas de voz o textos a Pilo durante el día ("compré 200 mil en pintura", "me pagaron la obra de la señora Marta, 1.5 millones") y Pilo registra ingresos y gastos categorizados. A fin de mes genera un reporte con totales, categorías, comparativo con meses anteriores y observaciones. **Canal:** número de Pilo. **Interfaz:** solo chat (menús para consultar, corregir, cerrar mes). **Posicionamiento obligatorio:** es una herramienta de REGISTRO y REPORTE, no de contabilidad ni asesoría tributaria. Todo reporte lleva esa aclaración; Pilo nunca da consejo tributario ni dice si algo es deducible.

### P4 — Cobrador

El dueño registra facturas pendientes (texto, foto de la factura, o carga masiva) y Pilo gestiona el recordatorio de cobro: define la secuencia (día 1 amable, día 7 firme, día 15 escalado al dueño), redacta cada mensaje con el tono correcto, y lleva el estado de cada cuenta por cobrar. **Canal de salida — REGLA DE ORO:** los mensajes de WhatsApp al deudor salen SIEMPRE desde el número del propio negocio (requiere tener P1 activo). Si el negocio no tiene su número conectado, Pilo envía por correo y/o deja el mensaje redactado para que el dueño lo envíe con un toque. **Nunca se envían mensajes de cobro desde el número de Pilo.**

**Razón de la regla:** contactar deudores desde nuestra WABA es mensajería no solicitada a terceros; los bloqueos y reportes degradan la calidad del número y pueden restringir la cuenta que sostiene TODOS los productos. Además, legalmente el acreedor es el negocio, no nosotros.

**Restricciones legales del Cobrador (implementar como reglas duras del sistema, Colombia):** solo horario hábil configurable (por defecto lun-sáb, 8am-7pm, nunca domingos ni festivos); frecuencia máxima configurable con tope duro (no más de un contacto por día ni más de 3 por semana por deudor); nunca contactar a terceros (familiares, referencias, empleadores del deudor); nunca lenguaje intimidante, amenazas de acciones legales o reporte a centrales de riesgo salvo texto legal que el dueño apruebe explícitamente; STOP inmediato si el deudor pide no ser contactado (marcar el caso y notificar al dueño); registro de auditoría de cada mensaje enviado. Al activar el producto, el dueño debe aceptar una declaración de que la deuda es real y suya, y que autoriza el contacto.

## 3. CÓMO SE VE PARA EL USUARIO

**Descubrimiento:** alguien le escribe al número de Pilo. Pilo se presenta, explica los oficios, responde precios y dudas, y si hay interés, captura el contacto y notifica al admin (a mí) para cerrar la venta o enviar el link de suscripción.

**Activación:** el dueño paga un producto. Su número queda en la whitelist de ese producto. Desde ese momento, al escribirle a Pilo, ve un menú distinto: los oficios que tiene contratados.

**Uso diario:**

- Con P3 activo, manda notas de voz y Pilo confirma cada registro ("Listo ✅ Gasto: $200.000 — Materiales"). Menús para ver el mes, corregir el último registro, pedir el reporte.
- Con P4 activo, manda la foto de una factura y Pilo la registra; le avisa qué cobros salieron y quién ya pagó.
- Con P1 o P2, Pilo le manda el link a su panel y le avisa por chat lo importante (escalaciones, cotización vista por el cliente).

**Un solo hilo de conversación, varios oficios.** Pilo entiende por contexto qué quiere el dueño; los menús son el respaldo, no el camino obligatorio.

## 4. ARQUITECTURA (qué construir)

### 4.1 Cuentas y accesos (el cambio estructural más importante)

Hoy el sistema asume "un negocio = un cliente del Recepcionista". Hay que generalizar a:

- `accounts`: la cuenta del cliente (el negocio). Datos, zona horaria, estado.
- `account_users`: personas asociadas a una cuenta, cada una con su `wa_id` (número de WhatsApp con el que le escriben a Pilo) y opcionalmente credenciales web. El `wa_id` es la llave de identificación en el chat.
- `products`: catálogo (P1..P4) con slug, nombre, descripción, precio, y si requiere panel.
- `subscriptions`: cuenta + producto + estado (trial/activa/vencida/cancelada) + periodo + precio pactado + método de pago. **La whitelist ES esta tabla:** un número tiene acceso a un producto si su cuenta tiene una suscripción activa a ese producto. No implementes una lista aparte.
- `payments`: pagos registrados por suscripción (manual al inicio: yo marco pagado; pasarela después).
- El `business` actual del Recepcionista pasa a colgar de `account` (una cuenta puede tener su negocio conectado o no).

### 4.2 El hub conversacional (número de Pilo)

- Segunda WABA/número en el sistema, marcado como `tipo: plataforma` (distinto de los números de clientes, `tipo: negocio`). El webhook ya existente debe rutear por `phone_number_id`: si es el número de Pilo → `PiloHubAgent`; si es de un cliente → el agente Recepcionista de ese negocio (comportamiento actual, no romperlo).
- `PiloHubAgent`: identifica el `wa_id` remitente → resuelve cuenta y suscripciones activas → arma el contexto y las herramientas disponibles **solo para los productos contratados** → responde.
- **Desconocido o sin suscripciones:** modo vitrina. Explica los productos, responde precios, captura interés, notifica al admin. Nunca ejecuta acciones de producto.
- **Reglas de sesión:** ventana de 24h de WhatsApp (los mensajes proactivos de Pilo —reporte mensual, avisos— requieren plantilla aprobada: prepararlas); manejo de menús con mensajes interactivos; comando universal "menu" para volver al inicio y "ayuda" para hablar con un humano.

### 4.3 Multimedia

- **Notas de voz (P3):** descargar el audio de la Cloud API → transcribir con un proveedor STT (Claude no procesa audio; usar Whisper API u otro, configurable en `config/pilo.php`) → pasar el texto al agente. Guardar transcripción y costo. Límite de duración configurable.
- **Imágenes de facturas (P4):** Claude sí procesa imágenes; enviar como bloque `image` con un prompt de extracción estructurada (proveedor/cliente, número, fecha, monto, vencimiento). Confirmación obligatoria del dueño por chat antes de guardar ("Encontré: Factura #123, $450.000, vence 30/07. ¿Correcto?"). Nunca dar por bueno un dato extraído sin confirmación.

### 4.4 Panel de administración (super admin)

Vista por producto: suscriptores, estado de suscripción, pagos del mes, uso (mensajes, transcripciones, tokens) y **costo de API por cuenta** vs. lo que paga → margen real por cliente. Alta manual de cuentas y suscripciones, activar/suspender productos, impersonar para soporte, y bandeja de leads capturados por el hub.

### 4.5 Costos a vigilar (implementar contadores desde el día uno)

Tokens de Claude por producto y cuenta, minutos de transcripción, conversaciones de WhatsApp con costo (plantillas), almacenamiento de medios. Sin esto, un cliente que manda 80 notas de voz diarias puede volverse no rentable sin que nos enteremos.

## 5. PLAN POR ETAPAS

**ETAPA A — Cimientos multi-producto (sin productos nuevos todavía).**
Modelo de cuentas/productos/suscripciones/pagos; migrar el Recepcionista actual a colgar de `account` sin romperlo; panel admin por producto; contadores de costo. Cierre: `migrate:fresh --seed` con datos demo de 2 cuentas y tests en verde.

**ETAPA B — El hub de Pilo.**
Segundo número tipo plataforma, ruteo en el webhook, `PiloHubAgent` con modo vitrina, identificación por `wa_id`, menús interactivos, captura de leads, comando "menu"/"ayuda". Sin productos conversacionales aún: si el usuario tiene P1/P2, Pilo le da el link a su panel y resuelve dudas. Cierre: conversación de punta a punta contra el número de Pilo con las tres situaciones (desconocido, cliente con P1, admin).

**ETAPA C — Cotizador (P2).**
Panel propio: carga de contexto comercial (catálogo, precios, mano de obra, márgenes, condiciones), generación de cotización desde una descripción en lenguaje natural, edición manual de ítems antes de emitir, PDF con la marca del NEGOCIO (no de Pilo), envío por correo/WhatsApp, historial y estados (borrador/enviada/aceptada/rechazada). Regla dura del agente: **jamás inventar precios**; si falta un precio, preguntar o dejar el ítem marcado para completar.

**ETAPA D — Copiloto financiero (P3).**
Transcripción de voz, extracción estructurada (tipo, monto, categoría, fecha, nota), confirmación por chat, corrección del último registro, consultas ("¿cuánto llevo gastado este mes?"), cierre de mes y reporte (PDF + resumen por chat) con la aclaración de que no es contabilidad ni asesoría tributaria. Categorías configurables por tipo de negocio.

**ETAPA E — Cobrador (P4).**
Registro de facturas (texto, imagen, carga masiva), secuencias de recordatorio configurables, redacción por tono y etapa, motor de envío que respeta la REGLA DE ORO (canal = número del negocio vía P1, o correo, o mensaje listo para que el dueño envíe), todas las restricciones legales de §2 como validaciones duras del sistema (no como instrucciones al prompt), STOP list, panel de cuentas por cobrar y auditoría completa de envíos.

**Fuera de alcance por ahora:** pasarela de pagos automática (Wompi/ePayco entra tras la Etapa C, cuando haya qué cobrar en volumen), app móvil, multi-idioma, integraciones contables externas.

## 6. REGLAS GENERALES PARA CLAUDE CODE

1. Etapa por etapa; resumen y OK entre cada una. No empieces una etapa nueva sin confirmación.
2. **No rompas el Recepcionista.** Es lo único en producción; cada etapa debe dejarlo funcionando y con sus tests en verde.
3. Los productos son módulos desacoplados: agregar un quinto producto no debe requerir tocar los otros cuatro. Contratos claros entre el hub y cada producto.
4. Las reglas legales y de seguridad (horarios de cobro, topes de frecuencia, STOP, confirmación de datos extraídos, canal de envío) se implementan como **validaciones de código**, nunca confiando solo en el prompt del modelo.
5. Todo texto de cara al usuario sigue `docs/BRAND.md` (voz de Pilo). Código en inglés, UI en español.
6. Si algo de este documento choca con la realidad del codebase o con límites de las APIs de Meta/Anthropic, dímelo y propón la alternativa antes de improvisar.
