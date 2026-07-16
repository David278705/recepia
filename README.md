# Pilo — Agente de IA recepcionista de WhatsApp 24/7

Pilo es un agente de IA que atiende por WhatsApp a los clientes de negocios
pequeños (barberías, clínicas estéticas, consultorios, restaurantes,
talleres): responde preguntas frecuentes usando solo la información que el
dueño configuró, propone horarios y agenda citas, y escala a un humano
cuando no sabe la respuesta, el cliente lo pide o detecta molestia/urgencia.
Cada negocio conecta su propio número de WhatsApp mediante la WhatsApp Cloud
API en modo **Coexistence** (el dueño sigue usando su app normal; el bot
responde por la API en el mismo número).

El MVP completo (fases 0-6 de `PLAN.md`) ya está implementado.

## Roles

- **`owner`** — dueño de negocio, uno por cuenta (`businesses.user_id` es
  único). Ve solo su negocio: dashboard, bandeja de conversaciones,
  calendario de citas y configuración del bot. No se autoregistra; el alta
  la hace el `super_admin`.
- **`super_admin`** — opera la plataforma completa desde `/admin/*`: alta de
  negocios (negocio + cuenta del dueño + credenciales de WhatsApp), métricas
  globales, costo de API, salud del sistema, e impersonación (entrar a la
  cuenta de un dueño para dar soporte).
- La columna `users.role` deja el camino listo para un futuro rol `staff`
  (todavía no implementado).

## Stack

- Laravel 12 + PHP 8.2 (XAMPP) como API JSON pura (`routes/api.php`)
- Vue 3 + Vue Router + Pinia — una sola SPA para los dos roles (dueño y
  admin), servida desde una única vista Blade
  (`resources/views/app.blade.php`) vía Vite — mismo origen, sin CORS
- Tailwind CSS v4 (tokens de color en `resources/css/app.css`)
- Laravel Sanctum (cookies SPA) para autenticación
- MySQL (base de datos `vigia`)
- Colas de Laravel (`QUEUE_CONNECTION=database`) — el webhook de WhatsApp
  responde 200 de inmediato y encola el procesamiento pesado
- Claude API (Anthropic), modelo `claude-haiku-4-5` por defecto (configurable
  por negocio) — el motor del agente recepcionista, con tool use
- WhatsApp Business Cloud API — conversación con clientes

## Estructura del dominio

- **Negocio**: `Business` (datos, tono, instrucciones extra del bot),
  `WhatsappAccount` (credenciales de WhatsApp por negocio, token encriptado),
  `Service`, `BusinessHour`, `Faq` (el "cerebro" del bot)
- **Conversación**: `Contact`, `Conversation` (`bot_activo` / `escalada` /
  `cerrada`, con `bot_paused_until` para el silencio de 30 min tras un echo
  del dueño), `Message` (idempotente por `wamid`), `Escalation`, `AgentLog`
  (request/response a Claude, para depurar prompts)
- **Agenda**: `Appointment`
- Todos los modelos "tenant-owned" usan el trait `BelongsToBusiness`, que
  aplica un Global Scope (`BusinessScope`) automático cuando el usuario
  autenticado es `owner` — ver [Seguridad](#seguridad-multi-tenant) abajo.

### Flujo de un mensaje entrante

1. Meta llama a `POST /api/webhooks/whatsapp`. Se valida la firma
   `X-Hub-Signature-256` con `WHATSAPP_APP_SECRET`; si no valida, 403.
2. Se responde 200 de inmediato y se encola `ProcessIncomingMessage`.
3. El Job identifica el negocio por `phone_number_id`, crea/reutiliza
   contacto y conversación, guarda el mensaje (ignora duplicados por
   `wamid`), y detecta si es un echo del dueño (coexistence) — si lo es, se
   guarda como `dueno_app` y el bot se silencia 30 min en esa conversación.
4. Si el bot debe responder y el mensaje es de texto, se invoca
   `ReceptionistAgent`, que arma el system prompt del negocio (servicios,
   horarios, FAQs, tono, instrucciones), ejecuta el loop de tool use contra
   Claude (`consultar_disponibilidad`, `agendar_cita`, `escalar_a_humano`) y
   envía la respuesta final por WhatsApp.
5. Si Claude falla por cualquier motivo, se escala la conversación y se le
   envía igual una respuesta al cliente — nunca se le deja sin respuesta.

## Puesta en marcha local

1. Copia `.env.example` a `.env` si aún no existe.
2. Instala dependencias:
   ```
   composer install
   npm install
   ```
3. Genera la key de la app si hace falta: `php artisan key:generate`.
4. Completa en `.env`:
   - `ANTHROPIC_API_KEY` — clave de la Claude API.
   - `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN` — de la app de Meta (ver
     abajo). Son a nivel de la app completa, no por negocio.
   - `SANCTUM_STATEFUL_DOMAINS` — dominios que reciben cookies de sesión SPA
     (ajusta el puerto al que uses, ej. `localhost:8096`).
   - Opcional: `WHATSAPP_DEMO_*` si quieres que el negocio de demo quede
     conectado a un número real desde el primer seed.
5. Migra y siembra los datos de demo:
   ```
   php artisan migrate:fresh --seed
   ```
   Esto crea:
   - Un `super_admin`: `jdavid.lozano1404@gmail.com` / contraseña en
     `ADMIN_PASSWORD` (o el fallback del seeder si no la defines).
   - Un negocio demo ("Barbería El Corte", dueño `demo@pilo.test` /
     `password`) con servicios, horarios, FAQs, tres contactos con
     conversaciones en distintos estados (bot activo, escalada, cerrada) y
     varias citas (pasadas y futuras).
6. Corre la app (necesitas Laravel + Vite + un worker de colas):
   ```
   php artisan serve
   npm run dev
   php artisan queue:work
   ```
7. Entra a `/login`. Como `super_admin` caes en `/admin/businesses`; como
   `owner` caes en el dashboard de tu negocio.

### Iterar el prompt del agente sin WhatsApp

```
php artisan pilo:simular "hola, tienen turno mañana?"
```

Ejecuta el agente contra el negocio demo (o el que indiques con
`--business=<slug|id>`) sin pasar por WhatsApp ni crear citas/escalaciones
reales — usa el modo dry-run del agente. Mantiene el hilo de la conversación
entre llamadas (guardado en `storage/app/pilo-simular/`); usa `--reset`
para empezar de cero. El panel también tiene un chat de prueba equivalente
en Configuración → "Probar mi bot".

## Configurar la app de Meta y el webhook

1. Crea una app en [Meta for Developers](https://developers.facebook.com/apps)
   y agrégale el producto **WhatsApp**.
2. En el panel de la app, copia el **App Secret** (Configuración básica) a
   `WHATSAPP_APP_SECRET` — firma todos los webhooks de esta app.
3. Define un **verify token** propio (cualquier string) y ponlo en
   `WHATSAPP_VERIFY_TOKEN`.
4. Para probar el webhook en local necesitas una URL pública. Con
   [ngrok](https://ngrok.com) (o Expose si prefieres PHP):
   ```
   ngrok http 8096
   ```
   Copia la URL HTTPS que te da (ej. `https://abcd1234.ngrok-free.app`).
5. En **WhatsApp → Configuración → Webhook** de la app de Meta:
   - Callback URL: `https://<tu-url-ngrok>/api/webhooks/whatsapp`
   - Verify token: el mismo valor de `WHATSAPP_VERIFY_TOKEN`
   - Suscríbete al campo `messages`
6. Meta hace un `GET` a esa URL para verificar (`hub.challenge`) — si tu
   servidor y el túnel están corriendo, debería quedar verificado al
   instante. Los eventos de mensajes empezarán a llegar por `POST`.

> El endpoint es único y global (`POST /api/webhooks/whatsapp`) — todos los
> negocios de la plataforma comparten la misma URL de callback; cada evento
> trae su propio `phone_number_id` para identificar a qué negocio pertenece.

## Conectar el primer negocio real (modo Coexistence)

El Embedded Signup automatizado de Meta queda fuera del MVP — el alta de
credenciales de WhatsApp es manual desde el panel de admin:

1. En el número de WhatsApp Business real del negocio, activa **Coexistence**
   desde la app de Meta Business Suite / WhatsApp Manager (el dueño sigue
   usando su app normal en el celular).
2. En **WhatsApp Manager**, genera un **token de acceso** para el número
   (permanente o de larga duración) y anota el `Phone Number ID` y el
   `WhatsApp Business Account ID (WABA ID)`.
3. En Pilo, entra como `super_admin` → **Panel de negocios** → **+ Nuevo
   negocio** (o edita uno existente) → sección **Conexión de WhatsApp**:
   completa Phone Number ID, WABA ID, número en formato E.164, y el token de
   acceso.
4. Guarda. El negocio queda con `whatsapp_accounts.connection_status =
   conectado`. Prueba enviándole un WhatsApp real al número — debería llegar
   el webhook y el bot responder según los datos configurados en
   **Configuración** de ese negocio (servicios, horarios, FAQs, tono).
5. Verifica el comportamiento de coexistence: si el dueño responde algo
   *desde su propia app* en una conversación, el bot debe silenciarse ahí
   por 30 minutos (`WHATSAPP_BOT_PAUSE_MINUTES`) — confírmalo con un mensaje
   de prueba antes de considerar el negocio listo para piloto.

## Seguridad multi-tenant

Esta es la frontera de seguridad más importante del sistema: que el dato de
un negocio nunca llegue al panel, al prompt o a las citas de otro.

- **Global Scope automático**: todos los modelos "tenant-owned"
  (`Service`, `BusinessHour`, `Faq`, `Contact`, `Conversation`, `Message`,
  `Appointment`, `Escalation`, `AgentLog`, `WhatsappAccount`) usan el trait
  `App\Models\Concerns\BelongsToBusiness`, que aplica un Global Scope
  (`BusinessScope`) filtrando automáticamente por el negocio del usuario
  autenticado cuando su rol es `owner`. `super_admin` y contextos sin
  usuario (jobs, consola, seeders) no se filtran — necesitan ver/crear datos
  de cualquier negocio. Cubierto por `tests/Feature/BusinessScopingTest.php`.
- **El agente y el Job del webhook corren sin usuario autenticado** (el
  Global Scope no aplica ahí), así que su aislamiento depende de que
  *siempre* se consulte a través de la relación del negocio
  (`$business->services()`, `$business->appointments()`, etc.) en vez de
  queries globales — así están escritos `ReceptionistAgent` y
  `ProcessIncomingMessage`. Verificado explícitamente como parte de la
  revisión de seguridad de la Fase 6.
- **Firma del webhook**: `POST /webhooks/whatsapp` valida
  `X-Hub-Signature-256` (HMAC-SHA256 con `WHATSAPP_APP_SECRET`) contra el
  body crudo; rechaza con 403 si falta, no valida, o el secreto no está
  configurado (falla cerrado, no abierto).
- **Rate limiting del webhook**: `throttle:whatsapp-webhook`
  (`WHATSAPP_WEBHOOK_RATE_LIMIT`, 300 req/min por IP por defecto) — es un
  límite agregado de toda la plataforma (no se puede limitar por negocio
  antes de identificarlo), pensado como defensa contra flood/abuso.
- **Tokens encriptados**: `whatsapp_accounts.access_token` usa el cast
  `encrypted` de Eloquent (Crypt de Laravel) — nunca se guarda en texto
  plano en la base de datos.
- **Route model binding + scoping**: los controladores del panel de `owner`
  siempre resuelven "el negocio del usuario autenticado" (sin `:id` suelto
  en la URL para el propio negocio) o dependen del Global Scope al bindear
  por ID (`Conversation $conversation`, `Appointment $appointment`, etc.) —
  un negocio nunca puede acceder por ID a un recurso de otro negocio
  (probado en `ConversationPanelTest`, `AppointmentPanelTest`).

### Antes de un piloto real o de subir el repo a un remoto compartido

- [ ] Rotar `ANTHROPIC_API_KEY`, `WHATSAPP_APP_SECRET` y cualquier token de
      WhatsApp que haya quedado en `.env` durante desarrollo — quedaron ahí
      como legado de la exploración inicial del proyecto.
- [ ] Confirmar que `.env` está en `.gitignore` (lo está) y que nunca se
      hizo `git add .env` antes de tenerlo ignorado.
- [ ] Cambiar la contraseña del `super_admin` sembrado por defecto
      (`ADMIN_PASSWORD` en `.env`, o la del seeder).
- [ ] Configurar `APP_DEBUG=false` en producción.
- [ ] Revisar que `SANCTUM_STATEFUL_DOMAINS` solo incluya los dominios reales
      de producción.

## Checklist de salida a piloto

- [ ] Negocio dado de alta con datos completos: nombre, tipo, dirección,
      teléfono, zona horaria correcta.
- [ ] Al menos un servicio con duración (y precio, si aplica).
- [ ] Horarios configurados para todos los días que el negocio atiende.
- [ ] Un puñado de FAQs con las preguntas que más hacen los clientes.
- [ ] Tono e instrucciones extra revisados con el dueño.
- [ ] WhatsApp conectado en modo Coexistence, con Phone Number ID, WABA ID,
      número E.164 y token verificados.
- [ ] Webhook de Meta suscrito a `messages` y verificado contra la URL de
      producción (no ngrok).
- [ ] Probado con al menos 3 conversaciones reales: una pregunta frecuente,
      un agendamiento completo, y un caso que fuerce escalación (para
      confirmar que el dueño recibe el email de aviso).
- [ ] Confirmado que un mensaje del dueño desde su propia app silencia al
      bot 30 minutos en esa conversación.
- [ ] `php artisan queue:work` corriendo de forma persistente (supervisor,
      systemd, o el gestor de procesos del hosting) — si se cae, los
      mensajes entrantes no se procesan.
- [ ] Dueño capacitado en el panel: bandeja de conversaciones, "Tomar
      conversación" / "Devolver al bot", calendario, configuración.

## Correr los tests

```
php artisan test
```

Cobertura actual: scoping multi-tenant, firma y rate limiting del webhook,
idempotencia y detección de echoes, el agente (FAQ, agendamiento, las dos
rutas de escalación, fallo de Claude), el panel (conversaciones, citas,
dashboard, bot de prueba), e impersonación.

## Producción

Compila los assets con `npm run build` (Vite genera `public/build/`,
servido automáticamente por la directiva `@vite` del shell). Necesitas un
worker de colas persistente (`php artisan queue:work`); no hay comandos
programados (`schedule:run`) en este MVP.
#   r e c e p i a  
 
## Fase 7 — Embedded Signup con coexistencia

Conectar el WhatsApp de un cliente ahora es un botón: el flujo oficial de Meta
(popup de Facebook Login for Business) con soporte de coexistencia. El alta
manual sigue disponible en el panel como "Alta manual / avanzada".

### Trámites externos (una sola vez, los hace el super_admin)

1. **Verificación del negocio** del Meta Business Manager:
   business.facebook.com → Configuración → Centro de seguridad → Iniciar verificación.
2. **Acceso avanzado** a `whatsapp_business_management` y
   `whatsapp_business_messaging`: developers.facebook.com → tu app → Revisión de app →
   Permisos y funciones (requiere screencast del flujo).
3. **Configuración de Facebook Login for Business**: developers.facebook.com →
   tu app → Facebook Login for Business → Configuraciones → crear una con los
   permisos de WhatsApp; copia el `config_id` resultante a `META_ES_CONFIG_ID`.

Variables: `META_APP_ID`, `META_APP_SECRET` (o se reutiliza
`WHATSAPP_APP_SECRET`), `META_GRAPH_VERSION`, `META_ES_CONFIG_ID`.

### Cómo se conecta un negocio

- Desde el panel: Admin → editar negocio → "Conectar WhatsApp (Embedded
  Signup)" → *Conectar aquí mismo*, o *Generar link para el dueño (48 h)* y
  enviárselo — el dueño lo abre en su computador con su propia sesión de
  Facebook, sin necesitar usuario en la plataforma.
- El backend canjea el code por el business token (encriptado), suscribe los
  webhooks de la WABA, detecta el modo (coexistencia vs dedicado — en
  coexistencia el registro del número se omite porque ya está registrado en la
  app) y deja bitácora por paso en `onboarding_logs`.

### Checklist de elegibilidad del número del cliente

- App **WhatsApp Business** v2.24.17 o superior, con el número activo ~7+ días.
- El número no debe haber estado en otra WABA recientemente (enfriamiento 1-2 meses).
- El **nombre del negocio en la app debe ser el definitivo antes de conectar**
  (queda bloqueado después).
- Tener a mano: sesión de Facebook con acceso al negocio y el celular para
  escanear el QR al final del popup.

### Guion de onboarding asistido

1. Verifica el checklist de elegibilidad con el dueño.
2. Genera el link firmado desde el panel y envíaselo (o hazlo en tu equipo con
   "Conectar aquí mismo" compartiendo pantalla).
3. El dueño: inicia sesión en Facebook → selecciona/crea su portafolio de
   negocio → elige "usar mi número actual de la app" → verifica → escanea el QR.
4. La pantalla muestra el progreso (canje, webhooks, verificación) y el estado
   final; al completarse llega un correo al super_admin.
5. Prueba de fuego: envía un mensaje al número y confirma que el bot responde.
6. Recuérdale al dueño abrir su app WhatsApp Business al menos cada 2 semanas
   (si no, la coexistencia se cae; el comando diario
   `pilo:verificar-conexiones` lo detecta y alerta por correo).
