# PROMPT PARA CLAUDE CODE — Transformar el proyecto existente en "Agente Recepcionista de WhatsApp"

> Cómo usarlo: crea una rama nueva (`git checkout -b recepcionista-ia`), guarda este archivo como `PLAN.md` en la raíz del repo, y dile a Claude Code: "Lee PLAN.md y ejecuta la FASE 0". Luego avanza fase por fase, revisando y confirmando entre cada una. No le pidas todo de una sola vez.

---

## CONTEXTO

Este repositorio contiene mi proyecto Laravel existente (Panthera Hub). Voy a reutilizarlo como base para construir un producto SaaS nuevo y diferente. Tu trabajo es transformarlo por fases, **sin romper lo reutilizable y sin borrar nada sin mi confirmación explícita**.

## EL PRODUCTO QUE VAMOS A CONSTRUIR

**Nombre provisional:** RecepIA (si encuentras el nombre actual "Panthera Hub" en el código, reemplázalo por este; el nombre final puede cambiar, así que centralízalo en `config/app.php` y variables de entorno, nunca hardcodeado en vistas).

**Qué es:** un agente de IA que actúa como recepcionista 24/7 por WhatsApp para negocios pequeños (barberías, clínicas estéticas, consultorios, restaurantes, talleres). Cada negocio conecta su propio número de WhatsApp mediante la **WhatsApp Cloud API en modo Coexistence** (el dueño sigue usando su app normal; el bot responde por la API en el mismo número). El agente:

1. Responde preguntas frecuentes (horarios, precios, servicios, ubicación) usando SOLO la información configurada del negocio.
2. Propone horarios disponibles y agenda citas en el calendario del negocio.
3. Escala a un humano (notifica al dueño y se silencia en esa conversación) cuando no sabe la respuesta, cuando el cliente lo pide, o cuando detecta molestia/urgencia.
4. Nunca inventa precios, servicios ni disponibilidad. Si un dato no está en su contexto, escala.

**Modelo de negocio:** multi-tenant. Yo (super admin) administro la plataforma; cada negocio es un tenant con su configuración, su número de WhatsApp y su panel.

**Stack:** Laravel (el de este repo) + colas (Redis/Horizon si ya existe, si no database queue) + MySQL/Postgres (el que ya use el repo) + Claude API (Anthropic) + WhatsApp Cloud API (Meta) + el frontend que ya use el repo (mantén el framework de frontend existente, solo rediseña).

---

## FASE 0 — EXPLORACIÓN Y PLAN DE DEMOLICIÓN (no escribas código de producto todavía)

1. Explora todo el repositorio y genera en `docs/AUDITORIA.md` un inventario: módulos, modelos, controladores, jobs, vistas, paquetes composer/npm, sistema de roles/permisos actual, y sistema de diseño actual (colores, layout, componentes).
2. Clasifica cada módulo en tres listas: **REUTILIZAR** (auth, roles, layout base, colas, notificaciones, billing si existe), **ADAPTAR** (renombrar/reestructurar), **ELIMINAR** (todo lo que no aporte al producto descrito arriba).
3. Preséntame las tres listas y **espera mi confirmación antes de eliminar cualquier cosa**.
4. Detecta y lista todo lugar donde aparezca el branding anterior (nombre, logo, colores) para el rebranding de la Fase 1.

## FASE 1 — LIMPIEZA Y REBRANDING

1. Elimina los módulos aprobados en Fase 0 (migraciones, modelos, rutas, vistas, seeds, tests asociados). Deja el repo compilando y los tests existentes en verde.
2. Rebranding completo:
    - Nombre: RecepIA (centralizado, no hardcodeado).
    - Paleta nueva (defínela como tokens/variables CSS en un solo archivo): un verde oscuro profesional como primario (evocando WhatsApp pero corporativo, ej. `#075E54` como base), un acento cálido (ej. ámbar `#F59E0B`), neutros grises cálidos, fondo claro por defecto. Tipografía sans limpia (la que ya tenga el repo o Inter).
    - Rediseña el layout base (sidebar/topbar) con la paleta nueva. Sobrio, denso en información, pensado para dueños de negocio no técnicos.
3. Roles nuevos (adapta el sistema de permisos existente):
    - `super_admin`: yo. Ve todos los negocios, métricas globales, costos de API, puede impersonar un negocio para soporte.
    - `owner`: dueño del negocio. Ve solo su negocio: conversaciones, citas, configuración, reportes.
    - (Deja la estructura lista para un futuro rol `staff`, pero no lo implementes aún.)

## FASE 2 — MODELO DE DATOS DEL PRODUCTO

Crea migraciones y modelos (con factories y seeders de demo) para:

- `businesses`: nombre, slug, tipo (barbería/clínica/restaurante/otro), dirección, teléfono, zona horaria (default America/Bogota), estado (piloto/activo/pausado), tono del bot (formal/cercano), instrucciones extra para el bot (texto libre del dueño).
- `whatsapp_accounts` (1:1 con business): phone_number_id, waba_id, número en formato E.164, token de acceso (encriptado con Crypt), verify_token del webhook, modo (coexistence/dedicado), estado de conexión.
- `services`: negocio, nombre, descripción, duración en minutos, precio (nullable — si es null el bot dice "el precio te lo confirma el equipo"), activo.
- `business_hours`: negocio, día de semana, apertura, cierre, activo (soportar múltiples franjas por día).
- `faqs`: negocio, pregunta, respuesta, activo. (Esta tabla + services + hours forman el "cerebro" del bot.)
- `contacts`: negocio, wa_id (número del cliente final), nombre (el que da WhatsApp o el que capture el bot), notas.
- `conversations`: negocio, contacto, estado (`bot_activo` / `escalada` / `cerrada`), última actividad, expiración de ventana de 24h.
- `messages`: conversación, dirección (in/out), origen (`cliente` / `bot` / `dueño_app` — los echoes de coexistence / `dueño_panel`), tipo (texto/imagen/audio/etc.), contenido, wamid (id de mensaje de Meta, único, para idempotencia), estado de entrega, tokens usados y costo estimado si lo generó el bot.
- `appointments`: negocio, contacto, servicio, inicio, fin, estado (propuesta/confirmada/cancelada/completada/no_asistió), origen (bot/panel), notas.
- `escalations`: conversación, motivo (no_sabe/cliente_lo_pidió/molestia/keyword), resuelto_en.
- `agent_logs` (opcional pero recomendado): request/response a Claude por mensaje, para depurar prompts.

Índices en todo lo que se consulte por negocio. Todas las queries del panel de `owner` deben estar scoped por su business (usa un Global Scope o política, y tests que lo verifiquen — es la frontera de seguridad multi-tenant más importante del sistema).

## FASE 3 — INTEGRACIÓN WHATSAPP CLOUD API

1. `WhatsAppService` (o Saloon/HTTP client dedicado): enviar texto, enviar botones/listas interactivas, marcar como leído. Config por negocio (cada business usa su propio phone_number_id y token).
2. Webhook único global `POST /webhooks/whatsapp`:
    - `GET` para la verificación de Meta (hub.challenge).
    - Validar firma `X-Hub-Signature-256` con el app secret. Rechazar si no valida.
    - Responder 200 inmediatamente y despachar un Job a la cola con el payload (Meta reintenta si tardas).
    - Idempotencia por `wamid` (ignorar mensajes ya procesados — con coexistence llegan echoes de lo que el dueño escribe desde su app: guárdalos como `dueño_app` pero NUNCA se los pases al bot como si fueran del cliente, y si el dueño escribe en una conversación, pausa el bot en esa conversación por 30 minutos).
3. Job `ProcessIncomingMessage`: identificar negocio por phone_number_id → cargar/crear contacto y conversación → guardar mensaje → decidir si el bot debe responder (¿conversación escalada? ¿bot pausado? ¿mensaje de tipo soportado?) → invocar al agente (Fase 4).
4. Mensajes no soportados en MVP (audio, imagen, ubicación): respuesta cortés fija ("¿Me lo puedes escribir en texto? 🙏") o escalar, configurable.
5. `.env.example` documentado con todas las variables (META_APP_SECRET, verify token, ANTHROPIC_API_KEY, etc.). Nunca comitees secretos.

## FASE 4 — EL AGENTE (Claude API)

1. `ReceptionistAgent` service. Modelo: `claude-haiku-4-5` por defecto (configurable por negocio para subir a Sonnet si hace falta).
2. System prompt construido dinámicamente por negocio: identidad ("Eres el asistente de {negocio}..."), tono configurado, servicios con duración y precio, horarios, FAQs, dirección, instrucciones extra del dueño, fecha/hora actual en la zona horaria del negocio, y reglas duras: responde SOLO con la información provista; si no sabes, usa la herramienta de escalar; nunca inventes precios ni disponibilidad; respuestas cortas estilo WhatsApp (1-3 oraciones, sin markdown); siempre en español.
3. Usa **tool use** de la API de Anthropic con estas herramientas:
    - `consultar_disponibilidad(servicio_id, fecha)` → devuelve slots libres calculados de business_hours menos appointments confirmadas.
    - `agendar_cita(servicio_id, inicio, nombre_cliente)` → crea appointment `confirmada`, responde confirmación, y encola notificación al dueño.
    - `escalar_a_humano(motivo)` → marca conversación como `escalada`, notifica al dueño (email en MVP; WhatsApp template después), y el bot responde "Ya le aviso a {dueño}, te contacta pronto 👍".
4. Contexto conversacional: últimos ~20 mensajes de la conversación. Ejecuta el loop de tool use (respuesta → tool_result → respuesta final) dentro del Job, con timeout y manejo de errores (si Claude falla, escalar, nunca dejar al cliente sin respuesta).
5. Guarda tokens y costo estimado por mensaje en `messages`/`agent_logs`.
6. Escribe tests del agente con la API mockeada: caso FAQ, caso agendamiento feliz, caso "no sé" → escala, caso cliente pide humano → escala, caso echo del dueño → bot se pausa.

## FASE 5 — PANEL

**Para `owner`:**

- Dashboard: conversaciones de hoy, citas de hoy/mañana, escalaciones pendientes (lo más prominente), contador de citas agendadas por el bot este mes (la métrica que justifica lo que paga).
- Bandeja de conversaciones estilo chat: lista + detalle, badges de estado (bot/escalada), botón "Tomar conversación" (pausa el bot) y "Devolver al bot", y envío manual de mensajes desde el panel (respetando la ventana de 24h: si expiró, deshabilitar y explicar por qué).
- Calendario de citas (vista semana) con crear/editar/cancelar manual.
- Configuración: datos del negocio, servicios, horarios, FAQs, tono e instrucciones del bot, y un botón "Probar mi bot" (chat de prueba en el panel que usa el mismo agente sin pasar por WhatsApp).
  **Para `super_admin`:** lista de negocios con estado de conexión, mensajes/mes, costo de API/mes, escalaciones; impersonación; y formulario de onboarding para conectar un negocio nuevo (crear business + registrar credenciales de WhatsApp manualmente; el Embedded Signup automatizado queda para después del MVP).

## FASE 6 — CIERRE DE MVP

- Seeders de demo completos (1 barbería con servicios, horarios, FAQs y conversaciones de ejemplo) para desarrollo y para mostrar el producto.
- Comando `php artisan recepia:simular "hola, tienen turno mañana?"` que ejecuta el agente por consola contra el negocio demo (para iterar prompts rápido sin WhatsApp).
- README nuevo: setup local, cómo configurar la app de Meta y el webhook (con ngrok/Expose en local), cómo conectar el primer negocio real en modo coexistence, checklist de salida a piloto.
- Revisión de seguridad: firma del webhook, tokens encriptados, scoping multi-tenant, rate limiting del webhook, y que ningún dato de un negocio pueda filtrarse al prompt de otro.

## REGLAS GENERALES PARA TODO EL TRABAJO

1. Trabaja fase por fase. Al terminar cada fase, resume qué hiciste, qué decisiones tomaste y qué falta, y espera mi OK.
2. No elimines nada sin confirmación (Fase 0 define la lista).
3. Cada fase debe dejar el proyecto migrable desde cero (`migrate:fresh --seed`) y con tests en verde.
4. Convenciones del repo existente: respeta el estilo de código, estructura de carpetas y frontend que ya usa el proyecto.
5. Textos de UI en español; código, nombres de clases y columnas en inglés.
6. Si algo del plan choca con la realidad del codebase, propón la alternativa antes de improvisar.
7. Fuera de alcance del MVP (no lo construyas aunque sea tentador): pagos/suscripciones, Embedded Signup automatizado, recordatorios de cita por template, soporte de audio con transcripción, multi-idioma, app móvil.

# FASE 7 — EMBEDDED SIGNUP PROPIO CON COEXISTENCIA (WhatsApp Cloud API)

> Cómo usarlo: agrega este archivo al repo (o pégalo al final de PLAN.md) y dile a Claude Code: "Lee FASE-7.md y ejecútala. Las fases 0-6 ya están completas."

---

## CONTEXTO

Las fases 0-6 del PLAN.md están completas: el sistema ya procesa mensajes por webhook, el agente responde y agenda, y el panel funciona. Hoy el alta de un negocio es manual (el super_admin pega phone_number_id, waba_id y token en un formulario). Esta fase reemplaza ese proceso por el flujo oficial **Embedded Signup de Meta con soporte de Coexistencia**, para que conectar el WhatsApp de un cliente sea un botón en nuestro panel.

**Qué es el flujo:** un popup oficial de Meta (SDK de JavaScript de Facebook) donde el dueño del negocio inicia sesión con su Facebook, crea/selecciona su Meta Business Manager, elige conectar su número existente de la app WhatsApp Business (eso activa la coexistencia), verifica el número y escanea un QR con su app. Al terminar, el popup nos entrega los identificadores y un código que canjeamos por un token para operar su WABA desde nuestra app.

**Importante — no elimines el alta manual existente.** Déjala como vía alternativa (etiquétala "Alta manual / avanzada" en el panel). El Embedded Signup pasa a ser la vía principal.

## PREREQUISITOS EXTERNOS (los hago yo, tú solo déjalos configurables)

Estos trámites son míos y pueden no estar listos cuando programes; el código debe quedar terminado y probable con mi cuenta de admin/testers aunque el acceso avanzado aún no esté aprobado:

- Verificación de negocio de mi Meta Business Manager (en curso).
- Acceso avanzado a `whatsapp_business_management` y `whatsapp_business_messaging` (en curso).
- Una configuración de **Facebook Login for Business** creada en mi app de Meta, que me da un `config_id`.

Variables de entorno nuevas (agrégalas a `.env.example` documentadas):

```
META_APP_ID=
META_APP_SECRET=        # ya debería existir de la validación de webhooks
META_GRAPH_VERSION=v23.0   # configurable
META_ES_CONFIG_ID=      # config_id del Embedded Signup (Facebook Login for Business)
```

## 1. FRONTEND — Página de conexión

1. Nueva pantalla "Conectar WhatsApp" accesible desde: (a) el detalle de un business en el panel de super_admin, y (b) una URL firmada temporal (signed URL de Laravel, expira en 48h) que el super_admin puede generarle a un dueño para que lo haga desde su propio computador con su propia sesión de Facebook. La pantalla NO requiere que el dueño tenga usuario en nuestra plataforma si entra por link firmado; el link ya viene atado a su business_id.
2. La pantalla carga el SDK de JavaScript de Facebook y lanza el flujo con `FB.login`, pasando:
    - `config_id` = META_ES_CONFIG_ID
    - `response_type: 'code'`, `override_default_response_type: true`
    - `extras: { "setup": {}, "featureType": "whatsapp_business_app_onboarding", "sessionInfoVersion": "3" }` — `featureType: whatsapp_business_app_onboarding` es lo que habilita la variante de coexistencia para números que ya viven en la app de WhatsApp Business. Verifica el nombre exacto del parámetro contra la documentación oficial vigente de "Embedded Signup — Onboarding business app users (Coexistence)" en developers.facebook.com antes de fijarlo; si difiere, usa el oficial.
3. Escucha los eventos `message` del popup (session info): captura `phone_number_id` y `waba_id` cuando el evento sea `WA_EMBEDDED_SIGNUP` con estado FINISH, y maneja explícitamente los estados de cancelación/abandono y error mostrando mensajes útiles.
4. Al terminar el popup, envía al backend: el `code` de la respuesta de FB.login + `phone_number_id` + `waba_id` + business_id (del contexto o del link firmado). Muestra una UI de progreso por pasos ("Canjeando autorización… Suscribiendo webhooks… Verificando número…") y un estado final de éxito o error.
5. Pantalla de error amigable con las causas comunes de inelegibilidad y qué hacer: app WhatsApp Business desactualizada (requiere v2.24.17+), número con menos de ~7 días de actividad real en la app, número que estuvo antes en otra WABA (enfriamiento de 1-2 meses), o cuenta/país no habilitado. Texto en español, tono simple para no técnicos.

## 2. BACKEND — Canje y aprovisionamiento

Endpoint autenticado/firmado `POST /whatsapp/onboarding/complete` que ejecuta, en un flujo idempotente y con manejo de errores por paso:

1. **Canje del code por token:** `GET /oauth/access_token` con app_id, app_secret y el code recibido. El token resultante es el business token del cliente para su WABA: guárdalo encriptado (Crypt) en `whatsapp_accounts`, nunca en logs.
2. **Suscripción de webhooks:** `POST /{waba_id}/subscribed_apps` con ese token. Sin este paso el número queda "sordo" — verifica la respuesta y falla con mensaje claro si no queda suscrita.
3. **Registro del número:** `POST /{phone_number_id}/register` con un PIN de verificación en dos pasos (genera uno por negocio y guárdalo encriptado). Consulta la documentación oficial de coexistencia sobre este paso: para números en modo coexistence el registro puede comportarse distinto que en la ruta clásica; implementa el manejo de ambos casos y no asumas.
4. **Verificación post-alta:** consulta el número (`GET /{phone_number_id}?fields=verified_name,display_phone_number,quality_rating,platform_type`) y guarda nombre visible, número E.164, y detecta/persiste el modo (coexistence vs dedicado) según lo que devuelva la API.
5. Actualiza `whatsapp_accounts` del business: estado `conectado`, modo, fecha de conexión. Si el business ya tenía credenciales manuales, pide confirmación antes de sobreescribir (flag en el request).
6. Registra todo el flujo en un log de onboarding (tabla o canal de log dedicado) para depurar altas fallidas: paso alcanzado, error de Meta (código y mensaje), sin tokens en claro.

## 3. POST-ONBOARDING Y MONITOREO

1. Al completar un alta: notificación al super_admin (email) con el resumen del negocio conectado.
2. Comando programado (diario) `recepia:verificar-conexiones`: para cada whatsapp_account conectado, consulta el estado del número contra la Graph API y marca/alerta si la conexión se degradó — el caso típico es que el dueño no abrió su app WhatsApp Business en ~13-14 días y la coexistencia se cayó. La alerta al super_admin debe decir exactamente eso y el remedio ("pídele al dueño abrir la app").
3. En el dashboard de super_admin, badge de salud de conexión por negocio (verde/amarillo/rojo) con fecha de última verificación y último mensaje recibido.

## 4. TESTS Y DOCUMENTACIÓN

1. Tests del endpoint de onboarding con la Graph API mockeada (Http::fake): flujo feliz, code inválido, fallo en subscribed_apps, fallo en register, reintento idempotente, y sobreescritura con/sin confirmación.
2. Test del link firmado: expira, atado al business correcto, inaccesible sin firma.
3. Actualiza el README con: los tres trámites externos y dónde se hacen (verificación de negocio, acceso avanzado con screencast, creación del config_id en Facebook Login for Business), el checklist de elegibilidad del número del cliente (versión de app, 7+ días de actividad, sin WABA previa, nombre del negocio correcto ANTES de conectar porque queda bloqueado), y el guion de la sesión de onboarding asistido de principio a fin.

## REGLAS

1. Respeta convenciones y estructura ya establecidas en el repo; UI en español, código en inglés.
2. Todo secreto en env; tokens y PIN encriptados en base de datos; jamás en logs.
3. Ante cualquier discrepancia entre este documento y la documentación oficial vigente de Meta (nombres de parámetros, versión del Graph API, pasos de coexistencia), gana la documentación oficial: señálamelo y ajusta.
4. Al terminar, resume qué construiste, qué quedó pendiente de mis trámites externos, y dame la lista exacta de pasos que yo debo hacer en los paneles de Meta para activar todo.
