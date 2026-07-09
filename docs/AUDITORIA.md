# AUDITORÍA — Vigia → RecepIA (FASE 0)

> Generado automáticamente por Claude Code el 2026-07-01, como primer paso del plan descrito en `PLAN.md`.

## ⚠️ Discrepancia importante con PLAN.md

`PLAN.md` asume que este repositorio se llama **"Panthera Hub"** y describe una exploración desde cero de un proyecto genérico. La realidad del codebase es distinta:

- El proyecto ya se llama **Vigia** y **ya es un SaaS funcional y específico**: un "Agente de Reputación 24/7" que monitorea reseñas de Google Business Profile, Facebook e Instagram, analiza sentimiento con Claude, redacta borradores de respuesta, alerta reseñas urgentes y genera reportes semanales.
- Las migraciones están fechadas **2026-07-01** (hoy) — es un codebase muy reciente, pequeño y limpio.
- Ya tiene **multi-tenancy funcional** (`Business.user_id` 1:1 con `User`), **roles** (`users.is_admin`), **integración con Claude** (`app/Services/Claude/*`), **integración con WhatsApp** (cliente saliente + webhook entrante, aunque hoy solo para alertas/aprobaciones, no conversación con clientes finales), colas, notificaciones por email, y un panel SPA Vue 3 con layouts separados para `owner` y `admin`.

Esto es una base **mucho más favorable** de lo que PLAN.md supone: en vez de partir de un esqueleto genérico, partimos de un producto con patrones (multi-tenant scoping, cliente Claude, cliente WhatsApp, jobs en cola, panel dueño/admin) directamente análogos a lo que RecepIA necesita. La Fase 1+ debe **adaptar** estos patrones en vez de construirlos desde cero.

**Riesgo detectado (fuera de alcance de Fase 0, para tu conocimiento):** el `.env` versionado en disco contiene claves aparentemente reales y activas (`ANTHROPIC_API_KEY`, `GOOGLE_PLACES_API_KEY`, `META_APP_SECRET`, `META_ACCESS_TOKEN`, `WHATSAPP_ACCESS_TOKEN`) y una contraseña de admin hardcodeada como fallback en el seeder (`Vigia$Admin2026#Lz`). Antes de subir este repo a un remoto (GitHub, etc.) o compartirlo, conviene rotar esas claves y confirmar que `.env` esté en `.gitignore`.

---

## 1. Identidad general

- **Nombre actual**: `Vigia` (`.env` / `.env.example`: `APP_NAME=Vigia`)
- **composer.json** `name`: sin personalizar, sigue en default `laravel/laravel`
- **package.json**: nombre inferido `vigia` (via package-lock)
- **Dominio actual**: monitoreo de reputación online (reseñas Google/Facebook/Instagram) con IA
- **Modelo de negocio actual**: multi-tenant ya funcional — un `owner` por negocio (`businesses.user_id` único), un `admin` global que gestiona todos los negocios desde `/admin/*`

## 2. Paquetes Composer

**require**: `php ^8.2`, `laravel/framework ^12.0`, `laravel/sanctum ^4.3` (auth SPA por cookie), `laravel/tinker ^2.10`

**require-dev**: `fakerphp/faker`, `laravel/pail` (logs en vivo), `laravel/pint` (formateo), `laravel/sail` (no usado activamente, es XAMPP), `mockery`, `nunomaduro/collision`, `phpunit ^11.5`

No hay: Spatie (permission/multitenancy), Cashier/Stripe, Horizon, librerías de Excel/PDF, Saloon. Las integraciones HTTP externas (Claude, Google, Meta, WhatsApp) están hechas a mano con el facade `Http` de Laravel dentro de `app/Services/`.

## 3. Paquetes NPM

**dependencies**: `@heroicons/vue`, `@vitejs/plugin-vue`, `pinia`, `vue ^3.5`, `vue-router ^5`

**devDependencies**: `@tailwindcss/vite`, `axios`, `concurrently`, `laravel-vite-plugin`, `tailwindcss ^4` (formato CSS-first `@theme`, sin `tailwind.config.js`), `vite ^7`

**Stack frontend confirmado**: SPA Vue 3 + Vue Router + Pinia servida desde una única vista Blade (`resources/views/app.blade.php`). No hay Inertia, Livewire, Alpine, React ni vistas Blade tradicionales.

## 4. Base de datos

**Migraciones núcleo Laravel (reutilizable sin cambios)**:
- `create_users_table`, `create_cache_table`, `create_jobs_table`, `create_personal_access_tokens_table` (Sanctum), `add_is_admin_to_users_table`

**Migraciones específicas del dominio Vigia (candidatas a eliminar/reemplazar)**:
- `create_businesses_table` — name, category, timezone, tone_description, google_place_id, facebook_page_id, instagram_business_id, whatsapp_notify_number, notify_email, response_mode, poll_interval_minutes, active, last_synced_at, user_id
- `create_reviews_table` — reseña con sentiment/ai_analysis/draft_response de Claude
- `create_weekly_reports_table` — insights semanales generados por Claude
- `create_support_tickets_table`
- `add_plan_fields_to_businesses_table` — urgent_rating_threshold, plan

**Seeders**: `DatabaseSeeder.php` crea un único usuario admin (sin negocios/reviews demo)

**Factories**: solo `UserFactory` (default de Laravel); no hay factories de Business/Review/WeeklyReport/SupportTicket

## 5. Modelos (`app/Models/`)

| Modelo | Propósito |
|---|---|
| `Business.php` | Negocio del dueño autenticado. Relaciones: owner, reviews, weeklyReports, supportTickets |
| `Review.php` | Reseña/mención con análisis de Claude. `isUrgent()`, `needsAlert()` |
| `WeeklyReport.php` | Resumen semanal generado por Claude |
| `SupportTicket.php` | Ticket de soporte registrado por admin |
| `User.php` | Auth (Sanctum), campo `is_admin`, relación `business()` (hasOne) |

Ningún modelo usa Spatie — el control de acceso es 100% el flag `is_admin` + middleware custom.

## 6. Controladores (`app/Http/Controllers/`)

**`Api/`** (dueño autenticado, siempre resuelve "el negocio del usuario"):
`AuthController`, `BusinessController` (show/update), `DashboardController` (show), `ReviewController` (index/approve/reject + `authorizeOwner()`), `WeeklyReportController` (index)

**`Api/Admin/`** (middleware `admin`):
`AvailableOwnersController`, `BusinessController` (CRUD completo), `MetricsController`, `SupportTicketController` (CRUD), `SystemHealthController`

**`Webhooks/`** (públicos, sin auth):
`MetaWebhookController` (verify/handle), `WhatsAppWebhookController` (verify/handle)

**Servicios** (`app/Services/`):
`Claude/ClaudeClient.php`, `SentimentAnalyzer.php`, `ResponseDrafter.php`, `WeeklyInsightsGenerator.php`, `GooglePlaces/GooglePlacesClient.php`, `Meta/MetaGraphClient.php`, `WhatsApp/WhatsAppClient.php`

## 7. Rutas

- `routes/web.php`: mínimo — ruta `login` + catch-all `/{any}` sirviendo el shell SPA
- `routes/api.php`: `POST /login` público → grupo `auth:sanctum` (`/logout`, `/user`, `/dashboard`, `/business`, `/business/reviews`, `/business/reports`) → sub-grupo `/admin` + middleware `admin` (`businesses`, `available-owners`, `metrics`, `system-health`, `support-tickets`) → webhooks públicos (`/webhooks/whatsapp`, `/webhooks/meta`)
- `routes/console.php`: comando `inspire` default + scheduler (`app:sync-reviews` cada 15 min, `app:send-weekly-reports` lunes 08:00)

La forma de las rutas (auth → panel scoped-por-tenant → panel admin → webhooks públicos) es exactamente la que RecepIA necesita; solo cambia el contenido del dominio.

## 8. Jobs / Colas / Eventos

**`app/Jobs/`** (todos del dominio reviews, candidatos a reemplazo):
`FetchGoogleReviews`, `FetchMetaMentions`, `AnalyzeReview`, `SendUrgentAlert`, `GenerateWeeklyReport`

**`app/Listeners/` y `app/Events/`**: no existen (no se usa sistema de eventos custom)

**`app/Console/Commands/`**: `SyncReviews.php`, `SendWeeklyReports.php`

**Colas**: driver activo `database` (`QUEUE_CONNECTION=database`). Redis está configurado en `.env` pero no es el driver de cola activo. No hay Horizon instalado.

**`app/Notifications/`**: `UrgentReviewNotification.php`, `WeeklyReportNotification.php` (ambas canal Mail)

## 9. Roles / Permisos

- No hay paquete de permisos (no Spatie, no ACL custom)
- Control de acceso: columna `users.is_admin` (boolean) + middleware `EnsureUserIsAdmin` (alias `admin`)
- Dos roles efectivos hoy: **admin** (`is_admin=true`) y **owner** (usuario normal con negocio vía `businesses.user_id`)
- Mapeo casi 1:1 con lo que pide PLAN.md: `admin` → `super_admin`, `owner` → `owner`. Es un rename trivial, no requiere Spatie ni reestructurar tablas.
- Scoping multi-tenant ya existe vía patrón `authorizeOwner()` / "resolver siempre el negocio del usuario autenticado sin `:id` en URL" — es exactamente el patrón de seguridad que PLAN.md pide para RecepIA (aunque hoy no está implementado como Global Scope automático, es mejora, no algo que falte crear desde cero).

## 10. Vistas / Frontend

- `resources/views/app.blade.php`: shell único, carga fonts (Bricolage Grotesque, Inter, JetBrains Mono), monta `#app` vía `@vite`. Título: `"Vigia · Agente de Reputación"`
- `resources/js/`: `app.js`, `App.vue`, `bootstrap.js`, `router/index.js`, `stores/auth.js`, `lib/api.js`
- `components/`: `AppLayout.vue` (layout dueño), `AdminLayout.vue`, `SidebarLayout.vue`, `Badge.vue`, `Button.vue`, `Card.vue`, `EmptyState.vue`, `ReviewCard.vue`, `SentimentBadge.vue`, `SlideOver.vue`, `Spinner.vue`, `StarRating.vue`, `TheSello.vue`
- `pages/`: `Login.vue`, `DashboardHome.vue`, `ReviewsInbox.vue`, `WeeklyReports.vue`, `AlertSettings.vue`, `BillingPlan.vue`, `BusinessProfile.vue`
- `pages/admin/`: `AdminBusinessesIndex.vue`, `AdminBusinessFormFields.vue`, `AdminSupportTicketsIndex.vue`, `AdminSupportTicketFormFields.vue`, `AdminMetrics.vue`, `AdminSystemHealth.vue`

**Sistema de diseño actual** (`resources/css/app.css`, Tailwind v4 `@theme`):
- Fuentes: `--font-sans: Inter`, `--font-display: Bricolage Grotesque`, `--font-mono: JetBrains Mono`
- Paleta **brand** (verde profundo): base ~`#2c6753` (600) / `#235244` (700)
- Paleta **mango** (acento, CTAs/urgencia): base ~`#f5901a` (500)
- Paleta **gold** (calificaciones): base ~`#e8b923` (500)
- Paleta **sand** (neutro cálido): `#faf8f5` (50) → `#221d18` (900)
- Componente `.sello`: badge circular rotado, "firma visual" del agente respondiendo reseñas (branding específico de Vigia, a redefinir)

**Nota clave**: la paleta verde-profundo existente (`#2c6753`) es notablemente cercana al verde WhatsApp corporativo que PLAN.md pide (`#075E54`). El sistema de tokens de color puede **reutilizarse casi tal cual** en vez de rehacerse desde cero.

## 11. Ocurrencias de branding "Vigia" (para Fase 1)

**Config / entorno**:
- `.env:1,25,68,87` — `APP_NAME`, `DB_DATABASE`, comentario, `WHATSAPP_VERIFY_TOKEN=vigia-verify-2026`
- `.env.example:1,5,26,70` — ídem + `APP_URL=http://localhost/vigia/public`
- `config/vigia.php` — archivo de config completo con namespace "vigia" (renombrar a `config/recepia.php`)

**Backend**:
- `database/seeders/DatabaseSeeder.php:23` — password de fallback `'Vigia$Admin2026#Lz'`
- `routes/console.php:11,13` — comentarios
- `app/Notifications/UrgentReviewNotification.php:30,36` — asunto/texto de email
- `app/Jobs/AnalyzeReview.php:23,43`, `SendUrgentAlert.php:35`, `FetchMetaMentions.php:34`, `FetchGoogleReviews.php:26` — logs (estos jobs son candidatos a ELIMINAR de todas formas)
- `app/Http/Controllers/Webhooks/WhatsAppWebhookController.php:49` — log
- `app/Models/Review.php:50` — `config('vigia.urgent_rating_threshold')`
- `app/Models/Business.php:63` — `config('vigia.poll_interval_minutes')`

**Frontend**:
- `resources/views/app.blade.php:6` — `<title>Vigia · Agente de Reputación</title>`
- `resources/js/components/SidebarLayout.vue:44,82` — texto de marca (ya soporta `brandSuffix` dinámico — buena señal, ya pensado para desacoplar el nombre)
- `resources/js/components/TheSello.vue:10` — "Vigia" en badge
- `resources/js/components/ReviewCard.vue:41` — "Borrador de Vigia"
- `resources/js/pages/BusinessProfile.vue:58`, `WeeklyReports.vue:51`, `ReviewsInbox.vue:47,56,63` — textos descriptivos
- `resources/js/pages/Login.vue:41` — `<h1>Vigia</h1>`

**No requieren edición manual (se regeneran)**: `public/build/assets/*.js`, `storage/framework/views/*.php`, `storage/logs/laravel.log`, `package-lock.json`

**Otros a revisar en Fase 1**: `public/favicon.ico` (contenido no verificado), nombre de carpeta del proyecto en disco (`vigia`) y base de datos MySQL (`vigia`) — fuera del alcance de "archivos de código" pero relevante para el rebranding completo.

## 12. Billing / Notificaciones / Multi-tenancy

- **Billing**: no hay Cashier/Stripe. Existe `BillingPlan.vue` y campo `businesses.plan` (default `'starter'`), pero es solo lectura — sin tabla de pagos ni cobro real (confirmado como "pendiente" en el README actual).
- **Notificaciones**: sistema estándar `Illuminate\Notifications`, canal Mail confirmado. El envío saliente de WhatsApp se hace directo desde el Job vía `WhatsAppClient`, no como Notification channel.
- **WhatsApp ya integrado**: `WhatsAppClient.php` (saliente) + `WhatsAppWebhookController.php` (entrante, verify/handle) + variables de entorno (`WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_BUSINESS_ACCOUNT_ID`). Hoy solo para alertas/aprobación de reseñas, no conversación con clientes — pero la infraestructura base es directamente adaptable al bot conversacional de RecepIA.
- **Multi-tenancy**: casera, vía `business_id` FK + scoping manual (`authorizeOwner()`), sin paquete dedicado. Funcional pero sin Global Scope automático (mejora pendiente, ya prevista en PLAN.md Fase 2).
- **Claude/Anthropic**: ya integrado en `config/services.php` (bloque `anthropic`) y `app/Services/Claude/*`.
- **Google Places / Meta Graph**: integrados pero específicos al dominio de reviews — candidatos a ELIMINAR para RecepIA.

## 13. Tests

- `tests/TestCase.php` — base estándar
- `tests/Feature/ExampleTest.php` — test default de Laravel (probablemente roto: verifica `/` con 200, pero ahora es un catch-all SPA)
- `tests/Unit/ExampleTest.php` — test default (`assertTrue(true)`)

**No hay cobertura real**: cero tests de Business, Review, WeeklyReport, SupportTicket, controladores API, webhooks, jobs, ni scoping multi-tenant. Toda la suite de tests para RecepIA se construye desde cero.

---

## Clasificación propuesta

### ✅ REUTILIZAR (sin cambios o casi sin cambios)

- Auth: Sanctum, `AuthController`, `stores/auth.js`
- Estructura de colas (`database` driver), sistema de `jobs`/`failed_jobs`
- Sistema de Notifications de Laravel (canal Mail)
- Stack frontend: Vue 3 + Vue Router + Pinia + Tailwind v4 + Vite, patrón de shell SPA único (`app.blade.php` + catch-all)
- Fuentes (Inter, Bricolage Grotesque, JetBrains Mono)
- Paleta de colores base (`brand` verde, `sand` neutros) — ajustar tonos si hace falta, no rehacer
- Componentes UI genéricos: `Badge.vue`, `Button.vue`, `Card.vue`, `EmptyState.vue`, `SlideOver.vue`, `Spinner.vue`
- Estructura de rutas: `Api/` (owner) vs `Api/Admin/` vs `Webhooks/` (públicos)
- Middleware `EnsureUserIsAdmin` (solo rename conceptual)
- `app/Services/Claude/ClaudeClient.php` como base del cliente Anthropic
- `app/Services/WhatsApp/WhatsAppClient.php` y `WhatsAppWebhookController.php` como base del cliente/webhook de WhatsApp (verify token, firma, estructura del handler)
- Migraciones núcleo: `users`, `cache`, `jobs`, `personal_access_tokens`, `add_is_admin_to_users_table`

### 🔧 ADAPTAR (mismo patrón, contenido/nombre nuevo)

- `Business` model + migración — ya tiene name/timezone/tono; falta tipo de negocio, dirección, teléfono, estado (piloto/activo/pausado), remover campos de reviews (google_place_id, facebook_page_id, instagram_business_id, poll_interval_minutes, response_mode, last_synced_at)
- `User.is_admin` → renombrar conceptualmente a roles `super_admin`/`owner` (dejar estructura lista para `staff` futuro, sin implementarlo, según PLAN.md)
- `WhatsAppClient`/`WhatsAppWebhookController` → extender de "solo alertas" a bot conversacional completo (idempotencia por wamid, manejo de echoes de coexistence, etc. — Fase 3)
- `ClaudeClient` → extender a `ReceptionistAgent` con tool use (Fase 4)
- `SidebarLayout.vue`, `AppLayout.vue`, `AdminLayout.vue` — mismo layout, nuevo copy/nav items
- `DashboardController`/`DashboardHome.vue` — misma idea (resumen para el dueño), métricas nuevas (conversaciones, citas, escalaciones en vez de reseñas)
- `Api/Admin/BusinessController`, `MetricsController`, `SystemHealthController` — mismo patrón CRUD/admin, adaptado a negocios de RecepIA
- Scheduler pattern en `routes/console.php` — reemplazar comandos de sync de reviews por los que necesite RecepIA (si aplica)
- Paleta de color: ajustar el verde `brand` hacia el tono WhatsApp-corporativo pedido, mantener estructura de tokens
- Nombre del proyecto: `Vigia` → `RecepIA` en todos los puntos listados en la sección 11

### 🗑️ ELIMINAR (candidatas — pendiente tu confirmación)

- Modelos: `Review.php`, `WeeklyReport.php`, `SupportTicket.php`
- Migraciones: `create_reviews_table`, `create_weekly_reports_table`, `create_support_tickets_table`, `add_plan_fields_to_businesses_table` (revisar si `plan` se reutiliza a futuro para billing de RecepIA o se elimina también)
- Controladores: `Api/ReviewController`, `Api/WeeklyReportController`, `Api/Admin/SupportTicketController`, `Webhooks/MetaWebhookController`
- Servicios: `GooglePlaces/GooglePlacesClient.php`, `Meta/MetaGraphClient.php`, `Claude/SentimentAnalyzer.php`, `Claude/ResponseDrafter.php`, `Claude/WeeklyInsightsGenerator.php`
- Jobs: `FetchGoogleReviews.php`, `FetchMetaMentions.php`, `AnalyzeReview.php`, `SendUrgentAlert.php`, `GenerateWeeklyReport.php`
- Comandos: `SyncReviews.php`, `SendWeeklyReports.php`
- Notifications: `UrgentReviewNotification.php`, `WeeklyReportNotification.php` (se reemplazan por notificaciones de escalación/citas en Fase 4-5)
- Vistas/componentes: `ReviewsInbox.vue`, `WeeklyReports.vue`, `TheSello.vue`, `ReviewCard.vue`, `SentimentBadge.vue`, `StarRating.vue`, `AlertSettings.vue` (revisar si se reconvierte a "configuración de escalación" en vez de eliminarse)
- `BillingPlan.vue` — mantener como placeholder vacío o eliminar (billing está fuera de alcance del MVP según PLAN.md regla 7)
- Variables de entorno específicas de reviews: `GOOGLE_PLACES_API_KEY`, `META_APP_SECRET`, `META_ACCESS_TOKEN`, etc. (las de WhatsApp SÍ se quedan, adaptadas)

---

## Próximo paso

Según PLAN.md, **no se elimina nada todavía**. Necesito tu confirmación explícita sobre las tres listas de arriba (especialmente los casos marcados "revisar" en ELIMINAR: `plan`/billing, `AlertSettings.vue`) antes de avanzar a la Fase 1 (limpieza + rebranding).
