# Auditoría de rebrand — RecepIA → Pilo

Inventario de las 155 ocurrencias de la marca anterior (`recepia`, case-insensitive)
en 43 archivos, clasificado según riesgo. Generado en la Etapa 0 del rebrand.

## (a) Renombrado trivial (textos / UI)

| Dónde | Qué |
|---|---|
| `resources/js/pages/*.vue` (Landing, Login, Terms, Privacy, Support, Subscription, ConnectWhatsApp, Forgot/ResetPassword) | Wordmark "recepia", textos "Recepia", correos `hola@recepia.app` |
| `resources/js/components/SidebarLayout.vue` | Wordmark del sidebar |
| `resources/views/app.blade.php` | `<title>`, meta tags |
| `app/Notifications/**` | Textos de correos que dicen "RecepIA"/"Recepia" |
| `app/Services/Claude/ReceptionistAgent.php` | Texto "Suscripción RecepIA" no está aquí, pero sí referencias de copy |
| `README.md`, `docs/AUDITORIA.md` | Documentación |
| `database/seeders/*` | Nombre en seeds de demo y default de `ADMIN_PASSWORD` |
| Mensajes de log `RecepIA:` en jobs/controladores/servicios | Prefijo de log |

## (b) Renombrado técnico con riesgo (ejecutado con mitigación)

| Qué | Riesgo | Decisión |
|---|---|---|
| `config/recepia.php` + todos los `config('recepia.*')` | Referencias rotas si queda alguna | Renombrado a `config/pilo.php` con reemplazo global verificado por grep y tests |
| Comandos artisan `recepia:*` | Scheduler y crons externos que los invoquen | Renombrados a `pilo:*`; scheduler (`routes/console.php`) y tests actualizados. **Manual:** si algún cron externo (Railway) llama `recepia:*`, actualizarlo |
| Prefijo de referencias Wompi `recepia-sub-{id}-...` | Transacciones PENDING creadas antes del deploy quedarían huérfanas | Renombrado a `pilo-sub-`; el parser acepta **ambos** prefijos durante la transición (ver `SubscriptionBiller::subscriptionFromReference`) — única ocurrencia legítima de la marca vieja en código |
| `composer.json` (name) | Ninguno real | Renombrado |
| `APP_NAME` | Se lee de env | `.env.example` actualizado; **manual:** actualizar `.env` local y variables de Railway |

## (c) NO tocar

- Identificadores externos de Meta (`phone_number_id`, `waba_id`, tokens, `WHATSAPP_VERIFY_TOKEN=vigia-verify-2026` — es un secreto compartido con Meta, cambiarlo rompe el webhook).
- Estructura de base de datos: no se renombran tablas ni columnas por marca (no existía ninguna con la marca).
- Credenciales y datos ya guardados (negocios llamados "Recepia" en BD son datos del usuario, no código).
- `PLAN.md`, `REBRAND.md` y este archivo: documentos históricos/prompt — ocurrencias permitidas.
- `.gitignore` (entrada de artefacto local).

## Excepciones permitidas al grep final

`PLAN.md`, `REBRAND.md`, `docs/AUDITORIA.md` (histórico de Fase 0), `docs/REBRAND-AUDIT.md`
(este archivo) y la compatibilidad de prefijo Wompi en `SubscriptionBiller`.
