# BRAND.md — Pilo

Fuente de verdad para todo copy y diseño futuro. Si algo no está aquí, pregunta antes de inventar.

## Concepto

**Pilo** viene del colombianismo "pilas / ser pilo": alguien atento, despierto, diligente,
que no se le escapa nada. Pilo es **el empleado más pilo del negocio**: el que nunca deja un
mensaje en visto, nunca olvida una cita y nunca duerme.

- **Personalidad:** cercano, resuelto, confiable. Un trabajador estrella — no un robot
  corporativo ni una caricatura infantil. La mascota es tierna; el tono transmite trabajo bien hecho.
- **Audiencia:** dueños de barberías, clínicas estéticas, consultorios, restaurantes, talleres
  y tiendas en Colombia/LatAm. Gente ocupada, no técnica, que vive en WhatsApp.

## Taglines

- **Principal:** "Pilo — el asistente que nunca deja tu WhatsApp en visto" (hero, login, título del sitio)
- Apoyo: "Tu negocio siempre responde"
- Apoyo: "El empleado más pilo de tu negocio"

## Dónde vive la marca en el código

- `config/brand.php` — nombre, tagline, dominio, correo de soporte, URLs legales (backend).
- `resources/js/lib/brand.js` — espejo para los componentes Vue.
- `APP_NAME=Pilo` en `.env`.
- Regla de oro: renombrar la marca cuesta esos dos archivos y una variable de entorno.

## Paleta (tokens en `resources/css/app.css`)

| Token | Hex | Uso |
|---|---|---|
| `--color-pilo-primary` (brand-600) | `#075e54` | Verde principal — nav, botones, confianza |
| `--color-pilo-primary-dark` (brand-800) | `#063d37` | Fondos oscuros, theme-color |
| `--color-pilo-accent` (amber-500) | `#f59e0b` | Acento cálido — CTAs, avisos |
| `--color-pilo-success` (brand-500) | `#157a68` | Éxito |
| `--color-pilo-warning` (amber-500) | `#f59e0b` | Alertas |
| `--color-pilo-error` | `#dc2626` | Errores |
| `--color-pilo-escalation` | `#e11d48` | **Escalación pendiente — el color más llamativo del sistema; solo para eso** |
| Neutros `sand-*` | `#faf8f5`–`#14110d` | Fondo y texto, cálidos |

Tipografía: **Inter** (texto), **Fraunces** (display/títulos), JetBrains Mono (números/moneda).
Esquinas suaves (`rounded-lg`–`rounded-2xl`), coherentes con la redondez de la mascota.
Nada de degradados estridentes ni estética de "startup genérica".

## La mascota (el robot)

Assets en `/public/img/` (`logo.png`, `logo-round.png`, `robot_*.png`).

**Reglas de uso:**
1. Máximo **una aparición por pantalla**.
2. Cada pose tiene un momento: `saludo_mano` (bienvenida/hero), `pensando_duda` (esperas, 404),
   `corriendo` (urgencia/plazo), `pulgar_arriba` (éxito/al día), `agradecido` (cierre/gracias),
   `sentado_laptop` / `tablet_*` (estados vacíos y trabajo).
3. El logo circular (`logo-round.png`) es el favicon, ícono PWA y avatar de marca.
4. No estirar, no recolorear, no ponerle texto encima.

## Guía de voz

Español colombiano neutro y cálido. Tutear siempre. Frases cortas. Máximo **un**
colombianismo por pantalla ("listo", "de una", "tranquilo"). Cero anglicismos innecesarios.
Los errores dicen qué pasó y qué hacer, sin culpar al usuario. Nunca infantil, nunca acartonado.

**10 ejemplos sí / no:**

| ✅ Sí | ❌ No |
|---|---|
| "Panel" | "Dashboard" |
| "Configuración" | "Settings" |
| "Listo, Pilo ya atiende con los cambios." | "¡Configuración actualizada exitosamente!" |
| "Un cliente necesita que lo atiendas tú" | "Alerta: conversación escalada por el sistema" |
| "Devolver a Pilo" | "Reactivar bot" |
| "Tu mes ya está pagado hasta el 7 de agosto." | "Error: operación de pago no permitida" |
| "No pudimos enviar el enlace. Intenta de nuevo." | "Fallo en el request. Contacte al administrador." |
| "Citas que Pilo agendó (mes)" | "KPI de conversiones automatizadas" |
| "Pilo se apartó y le avisó al cliente que lo contactarás." | "El agente conversacional fue pausado." |
| "El asistente que nunca deja tu WhatsApp en visto" | "IA conversacional multi-tenant para PYMEs" |

## Regla crítica — la marca frente al cliente final

En WhatsApp, el agente se presenta como **asistente DEL NEGOCIO** ("Soy el asistente de
{negocio}"), **no** como Pilo. Solo si el dueño activa el toggle **"Presentar a Pilo con su
nombre"** (campo `show_brand`, default apagado) dice "Soy Pilo, el asistente de {negocio}".
La marca Pilo es para el dueño que paga; el cliente final le pertenece al negocio.
