# Prompt inicial para la sesión autónoma

> Copia el bloque de abajo tal cual como primer mensaje de la sesión de Claude Code.

---

Vas a trabajar solo durante varias horas en este repositorio. No voy a estar disponible: **no me hagas preguntas, porque nadie las va a responder**. Preguntar es perder la sesión entera.

Lee y aplica la skill `trabajo-autonomo` (en `.claude/skills/trabajo-autonomo/`) durante toda la sesión. Es tu manual de operación: define cómo elegir tareas, qué estás autorizado a hacer, qué está prohibido, cómo decidir cuando falta información y cómo registrar el trabajo. Léela completa antes de tocar nada, junto con sus referencias `calidad.md` y `contexto-pilo.md`.

**Tu misión esta noche:** integrar y refinar al máximo lo que ya existe. No construir funcionalidades nuevas grandes: consolidar, endurecer y mejorar. Concretamente, quiero que el proyecto amanezca más sólido, más probado, más seguro y más coherente de lo que está ahora.

**Estás explícitamente autorizado** a tomar iniciativa: si analizando el código ves una mejora real —una buena práctica que falta, un bug latente, un riesgo de seguridad, código duplicado, falta de tests, manejo de errores ausente, una consulta ineficiente, un texto de UI confuso— **hazla sin pedirme permiso**, siempre que:
- esté dentro del alcance y propósito del proyecto,
- no rompa nada que hoy funcione,
- no cruce ninguna de las líneas rojas de la skill,
- y quede registrada y sea reversible.

Ante cualquier duda entre dos caminos: elige el más conservador y reversible, anótalo en `docs/DECISIONES.md`, y sigue adelante. Si algo requiere criterio de negocio o mío (precios, marca, prioridades, trámites externos, cualquier cosa irreversible fuera del repo), no lo abordes: déjalo en `docs/PENDIENTE-HUMANO.md` y pasa a la siguiente tarea.

**Antes de empezar, prepara el terreno:**
1. Confirma que estás en una rama de trabajo dedicada; si no, créala: `git checkout -b autonomo/AAAA-MM-DD`. No trabajes ni hagas push a main.
2. Corre la suite de tests y anota el estado inicial. Si algo está roto de entrada, arreglarlo es tu primera tarea.
3. Revisa `docs/BACKLOG.md`, `docs/WORKLOG.md` y `docs/DECISIONES.md` para saber en qué quedó todo.

**Durante la sesión:** una tarea a la vez, commits atómicos, tests en verde antes de cada commit, y una entrada en `docs/WORKLOG.md` por cada tarea terminada. Nunca dejes el repo roto.

**Al terminar** (o cuando notes que se te agota el contexto, sin esperar al último momento): escribe un `## RESUMEN DE SESIÓN` al inicio de `docs/WORKLOG.md` con qué lograste, qué quedó a medias y en qué estado, qué debo revisar primero, y qué sigue.

Empieza ahora.
