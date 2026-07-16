# REBRAND — De "RecepIA" a "Pilo": nombre, identidad visual y voz de marca

> Cómo usarlo: guarda este archivo como `REBRAND-PILO.md` en la raíz del repo y dile a Claude Code: "Lee REBRAND-PILO.md y ejecuta la Etapa 1". Avanza etapa por etapa, confirmando entre cada una. Antes de arrancar: crea rama `git checkout -b rebrand-pilo` y coloca los archivos de la mascota en `/public/brand/` (ver Etapa 0).

---

## CONTEXTO

Este proyecto Laravel es una plataforma SaaS multi-tenant: un recepcionista con IA que atiende el WhatsApp de negocios pequeños (responde, agenda citas, escala a humanos). Hasta hoy se llama **RecepIA**. La marca definitiva es **Pilo**. Tu trabajo: renombrar todo el sistema y darle una identidad visual y verbal coherente con la nueva marca, sin romper nada funcional.

## LA MARCA

**Nombre:** Pilo. Viene del colombianismo "pilas / ser pilo": alguien atento, despierto, diligente, que no se le escapa nada. Pilo es "el empleado más pilo del negocio": el que nunca deja un mensaje en visto, nunca olvida una cita y nunca duerme.

**Personalidad:** cercano, resuelto, confiable. Un trabajador estrella, no un robot corporativo ni una caricatura infantil. La mascota es tierna, pero el tono transmite trabajo bien hecho.

**Audiencia:** dueños de barberías, clínicas estéticas, consultorios, restaurantes y talleres en Colombia/LatAm. Gente ocupada, no técnica, que usa WhatsApp todo el día.

**Taglines de referencia** (usa el principal en el hero/login; los otros como apoyo donde encajen):

- Principal: **"Pilo — el asistente que nunca deja tu WhatsApp en visto"**
- "Tu negocio siempre responde"
- "El empleado más pilo de tu negocio"
  Puedes proponer variantes mejores siguiendo la voz de marca, preséntamelas antes de fijarlas.

**Mascota:** ya existe (el robot que está como ícono). Yo dejaré los archivos en `/public/brand/` (versiones en PNG/SVG que tenga). Si al ejecutar no encuentras la carpeta o faltan tamaños/variantes, avísame qué necesitas exactamente (ej. versión sobre fondo oscuro, versión solo-cara para favicon) en lugar de inventar o generar imágenes.

## ETAPA 0 — AUDITORÍA (no cambies nada todavía)

1. Busca TODAS las ocurrencias de la marca anterior en el repo, case-insensitive y en variantes: `RecepIA`, `Recepia`, `recepia`, `recep-ia`, `RECEPIA` — en código, vistas, configs, `.env.example`, seeds, factories, tests, comandos artisan (`recepia:*`), nombres de clases, traducciones, correos, README, `docs/`, manifest PWA, `composer.json`/`package.json` (campo name), títulos HTML, páginas legales.
2. Genera `docs/REBRAND-AUDIT.md` con el inventario clasificado en: (a) renombrado trivial (textos/UI), (b) renombrado técnico con riesgo (clases, comandos, claves de config, colas/jobs con nombre), (c) NO tocar (identificadores externos de Meta, credenciales, migraciones ya ejecutadas — no renombres tablas ni columnas por marca).
3. Preséntame el inventario y espera mi OK.

## ETAPA 1 — RENOMBRADO TÉCNICO

1. **Centraliza el nombre**: `APP_NAME=Pilo` en `.env`/`.env.example`; toda referencia visible debe salir de `config('app.name')` o de un único archivo `config/brand.php` nuevo (nombre, tagline, dominio, correo de soporte, URLs legales, redes). Cero hardcodeo del nombre en vistas, correos o strings — la regla de oro: **renombrar la marca en el futuro debe costar una sola línea**.
2. Renombra comandos artisan `recepia:*` → `pilo:*` (ej. `pilo:simular`, `pilo:verificar-conexiones`), actualizando scheduler, docs y tests.
3. Renombra clases/namespaces que contengan la marca vieja solo si el riesgo es bajo; si alguno es delicado (serialización de jobs en colas, por ejemplo), proponme la alternativa antes.
4. Placeholders de contacto: usa `soporte@soypilo.com` y dominio `soypilo.com` en `config/brand.php` (los cambiaré cuando confirme el dominio final — por eso van centralizados).
5. Actualiza README, seeds de demo y páginas legales (política de privacidad, términos, eliminación de datos) con el nuevo nombre.
6. Al terminar: `migrate:fresh --seed` funcionando y tests en verde. Ninguna ocurrencia de la marca vieja debe sobrevivir (`grep -ri recepia` limpio, salvo el archivo de auditoría).

## ETAPA 2 — IDENTIDAD VISUAL

1. **Extrae la paleta de la mascota**: examina los archivos de `/public/brand/` y deriva los tokens de color desde ahí, manteniendo la familia verde (mundo WhatsApp) como base. Define en UN solo archivo de tokens CSS: `--pilo-primary`, `--pilo-primary-dark`, `--pilo-accent`, neutros, y colores semánticos (éxito/alerta/error/escalación). La escalación pendiente debe tener el color más llamativo del sistema — es la métrica que el dueño no puede ignorar.
2. **Integra la mascota** en: logo del navbar/sidebar (con el wordmark "Pilo"), pantalla de login, favicon (genera los tamaños desde el asset), estados vacíos del panel (ej. bandeja sin conversaciones: mascota + "Pilo está atento. Cuando llegue el primer mensaje, lo verás aquí."), encabezado de correos, página 404, y la pantalla del chat de prueba ("Probar mi bot"). No la satures: máximo una aparición por pantalla.
3. **Refina el layout** con los tokens nuevos: sobrio, denso en información, para dueños no técnicos. Tipografía actual (o Inter) se mantiene. Botones y estados con esquinas suaves coherentes con la redondez de la mascota. Nada de degradados estridentes ni modo "startup genérica".
4. Actualiza manifest PWA, meta tags (og:title, og:image con la mascota, theme-color) y título del sitio.

## ETAPA 3 — VOZ Y MICROCOPY

Reescribe todos los textos de UI siguiendo esta guía de voz:

**Reglas de voz:** español colombiano neutro y cálido; tutear siempre; frases cortas; palabras como "listo", "de una", "tranquilo" bienvenidas con moderación (máximo un colombianismo por pantalla); cero anglicismos innecesarios (no "dashboard" → "panel"; no "settings" → "configuración"); nunca infantil, nunca acartonado. Los mensajes de error dicen qué pasó y qué hacer, sin culpar al usuario.

Aplícala en:

1. **Panel completo**: navegación, dashboard (ej. "Citas que Pilo agendó este mes"), bandeja de conversaciones, botones ("Tomar conversación" / "Devolver a Pilo"), formularios de configuración, toasts (éxito: "Listo, Pilo ya está atendiendo con los cambios").
2. **Correos transaccionales**: bienvenida, alerta de escalación ("Un cliente necesita que lo atiendas tú"), resumen, alerta de conexión caída.
3. **Onboarding/conexión de WhatsApp**: los pasos y errores del flujo, en lenguaje de dueño de negocio, no de desarrollador.
4. **Landing** (si existe en el repo): hero con tagline principal, beneficios en lenguaje de plata y tiempo ("no pierdas la cita de las 9pm que llegó a las 11pm"), no en lenguaje técnico ("IA conversacional multi-tenant" prohibido).

**Regla crítica del bot de cara al cliente final:** en WhatsApp, el agente se presenta como asistente DEL NEGOCIO ("Hola 👋 Soy el asistente de {nombre_negocio}, ¿en qué te ayudo?"), NO como "Pilo". Agrega en la configuración del negocio un toggle `mostrar_marca` (default: apagado) que, si el dueño lo activa, cambia la presentación a "Soy Pilo, el asistente de {negocio}". La marca Pilo es para el dueño que paga; el cliente final le pertenece al negocio.

## ETAPA 4 — CIERRE

1. Barrido final: `grep -ri recepia` limpio; captura de pantallas clave (login, dashboard, bandeja, correo) para mi revisión.
2. Genera `docs/BRAND.md`: resumen de la marca (concepto, paleta con hex, tokens, guía de voz con 10 ejemplos de sí/no, reglas de uso de la mascota) — la fuente de verdad para todo copy futuro.
3. Entrégame la **lista de tareas manuales fuera del repo** que me quedan a mí, al menos: cambiar el nombre visible de la app en developers.facebook.com (y verificar que las URLs de privacidad/términos apunten a las páginas nuevas), registrar dominio y handles definitivos y actualizarlos en `config/brand.php`, regenerar el enlace de Embedded Signup si cambia el dominio del callback, y actualizar el nombre en cualquier BSP o material externo.

## REGLAS GENERALES

1. Etapa por etapa; resume y espera mi OK entre cada una. Nada se elimina sin pasar por la auditoría de la Etapa 0.
2. No toques: credenciales, tokens, identificadores de Meta, estructura de base de datos, ni la lógica del agente — esto es rebranding, no refactor funcional.
3. UI en español, código en inglés. Tests en verde al cierre de cada etapa.
4. Si un texto o decisión de diseño te genera duda entre dos opciones, muéstrame ambas con tu recomendación en vez de decidir en silencio.
