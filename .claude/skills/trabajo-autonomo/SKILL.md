---
name: trabajo-autonomo
description: Protocolo de trabajo autónomo sin supervisión para el proyecto Pilo. Úsalo SIEMPRE que trabajes en este repositorio sin un humano disponible para responder preguntas — es decir, en cualquier sesión donde no recibas respuesta a una pregunta en el mismo turno. Define cómo elegir la siguiente tarea, qué está autorizado hacer sin pedir permiso, qué está terminantemente prohibido, cómo decidir cuando falta información, y cómo dejar registro del trabajo. Se aplica a refactors, mejoras de calidad, tests, documentación, corrección de bugs y avance del backlog.
---

# Protocolo de trabajo autónomo — Pilo

## Situación

Estás trabajando solo. El dueño del proyecto está dormido o ausente y **no va a responder nada** durante esta sesión. Preguntar equivale a detenerte, y detenerte es el peor resultado posible: desperdicia la sesión completa.

Tu misión: dejar el proyecto **mejor de lo que lo encontraste**, sin romper nada, y con un registro claro de todo lo que hiciste y por qué.

## Regla número uno

**Nunca preguntes. Decide, actúa y documenta.**

Cuando enfrentes una decisión sin respuesta obvia:
1. Elige la opción **más conservadora y reversible**.
2. Anótala en `docs/DECISIONES.md` con: qué decidiste, qué alternativas había, por qué elegiste esa, y cómo revertirla.
3. Sigue trabajando.

Si una tarea es **imposible** de decidir sin criterio de negocio (precios, modelo comercial, nombres de marca, prioridades de producto, algo que involucre dinero real o cuentas externas), no la abordes: anótala en `docs/PENDIENTE-HUMANO.md` con el contexto necesario para que se decida en 30 segundos, y **pasa a la siguiente tarea del backlog**.

## Ciclo de trabajo

Repite este ciclo hasta que se acabe la sesión:

1. **Orientarte** (solo al inicio de la sesión): lee `docs/WORKLOG.md` (últimas entradas), `docs/BACKLOG.md`, `docs/DECISIONES.md` y `CLAUDE.md`. Verifica el estado del repo: `git status`, rama actual, tests pasando.
2. **Elegir una tarea** según la prioridad de abajo. Una sola tarea a la vez.
3. **Acotar**: si la tarea es grande, divídela y toma solo la primera porción. Nada de refactors gigantes en un commit.
4. **Ejecutar** con las reglas de calidad de `references/calidad.md`.
5. **Verificar**: la suite de tests completa debe pasar. Si tocaste el esquema, `migrate:fresh --seed` debe funcionar.
6. **Commit atómico** con mensaje descriptivo en español (ver formato abajo).
7. **Registrar** en `docs/WORKLOG.md`.
8. **Volver al paso 2.**

## Prioridad de tareas

Cuando termines algo, elige la siguiente en este orden:

1. **Reparar lo roto**: tests fallando, errores, migraciones que no corren, código que no compila. Siempre primero.
2. **Riesgos de seguridad o pérdida de datos**: secretos expuestos, validación faltante en entradas, fugas entre tenants (que la cuenta A pueda ver datos de la B es el bug más grave posible en este sistema), tokens sin encriptar, endpoints sin autorización.
3. **Tareas explícitas del backlog** (`docs/BACKLOG.md`), en orden.
4. **Cobertura de tests** en la lógica crítica: agente, webhooks, aislamiento multi-tenant, agendamiento, cálculo de disponibilidad.
5. **Deuda técnica y buenas prácticas**: duplicación, funciones enormes, lógica de negocio en controladores, N+1 queries, índices faltantes, manejo de errores ausente, valores mágicos hardcodeados.
6. **Documentación**: README, docs de arquitectura, PHPDoc en lo no obvio, `.env.example` completo.
7. **Pulido de UI y microcopy** siguiendo `docs/BRAND.md`.

Si el backlog está vacío y no encuentras nada de lo anterior, **audita**: recorre un módulo a fondo buscando problemas reales y agrégalos al backlog con diagnóstico. No inventes trabajo cosmético ni reescribas código que funciona solo por preferencia de estilo.

## Autorizado sin preguntar

- Crear, refactorizar y reorganizar código dentro del alcance del proyecto.
- Añadir tests, factories, seeders de desarrollo.
- Corregir bugs, incluyendo cambiar la implementación si el comportamiento correcto es evidente.
- Extraer servicios, aplicar patrones del framework, mejorar nombres internos.
- Añadir migraciones **aditivas** (tablas nuevas, columnas nuevas nullable, índices).
- Mejorar validaciones, manejo de errores, logging.
- Escribir y reorganizar documentación.
- Mejorar textos de UI siguiendo la guía de marca.
- Optimizar consultas y rendimiento.

## Prohibido (líneas rojas — nunca, bajo ninguna justificación)

1. **Nunca borres datos ni estructuras con datos**: nada de `drop`, `truncate`, borrar columnas o tablas existentes, ni migraciones destructivas. Si algo sobra, márcalo como obsoleto y anótalo en `PENDIENTE-HUMANO.md`.
2. **Nunca toques secretos**: no leas, muevas, imprimas ni commitees `.env`, tokens, claves ni credenciales. `.env.example` sí, con placeholders.
3. **Nunca hagas push a `main`/`master` ni merges.** Trabaja solo en la rama de la sesión.
4. **Nunca ejecutes nada contra producción** ni contra APIs externas reales (Meta, Anthropic, pasarelas de pago) con credenciales reales. Los tests usan mocks/fakes, siempre.
5. **Nunca hagas trámites externos ni acciones irreversibles fuera del repo**: no crear cuentas, no enviar mensajes reales, no publicar nada, no modificar configuraciones en paneles de terceros.
6. **Nunca debilites las reglas de seguridad, legales o de negocio** definidas en la documentación del proyecto (aislamiento entre cuentas, reglas de cobranza, validación de firmas de webhook, confirmaciones obligatorias del usuario). Puedes reforzarlas, nunca relajarlas.
7. **Nunca cambies decisiones de producto o modelo comercial**: precios, nombres de productos, alcance de planes, marca.
8. **Nunca agregues dependencias pesadas** (frameworks, librerías grandes, cambios de stack). Una librería pequeña y muy usada para un problema concreto está bien: justifícala en el WORKLOG. Ante la duda, no la agregues.
9. **Nunca reescribas desde cero** un módulo que funciona. Refactor incremental, siempre.
10. **Nunca dejes el repo roto** al terminar una unidad de trabajo.

## Formato de commits

```
tipo(ámbito): descripción breve en español

Por qué se hizo, en 1-3 líneas. Decisiones no obvias.
```
Tipos: `fix`, `feat`, `refactor`, `test`, `docs`, `perf`, `chore`, `style`.
Un commit = un cambio coherente. Nunca mezcles refactor con cambio de comportamiento.

## Registro obligatorio

Al final de **cada tarea**, agrega al inicio de `docs/WORKLOG.md`:

```markdown
## [YYYY-MM-DD HH:MM] Título de la tarea
- **Qué**: qué cambió, en una o dos frases.
- **Por qué**: el problema que resuelve.
- **Archivos**: los principales tocados.
- **Riesgo**: bajo/medio/alto + qué revisar manualmente si aplica.
- **Commit**: hash.
```

Al final de **la sesión** (o cuando notes que se agota el contexto), escribe un bloque `## RESUMEN DE SESIÓN` al inicio del WORKLOG con: qué se logró, qué quedó a medias y en qué estado, qué revisar primero al despertar, y qué sigue en el backlog.

## Si algo sale mal

- **Tests que no logras arreglar tras 3 intentos serios**: revierte tu cambio (`git revert` o descarta), anota el problema en el backlog con tu diagnóstico, y pasa a otra tarea. No dejes el repo rojo.
- **Te encuentras un problema grande fuera del alcance actual**: no lo ataques a medias. Documéntalo en el backlog con detalle y sigue.
- **Sospechas que un cambio tuyo pudo romper algo sutil**: revierte. Lo reversible siempre gana.
- **Te quedas sin contexto**: escribe el resumen de sesión ANTES de que sea tarde. Un traspaso claro vale más que una tarea extra a medio hacer.

## Consulta

- `references/calidad.md` — estándares de código, tests y revisión propia antes de cada commit.
- `references/contexto-pilo.md` — qué es el proyecto, qué es sagrado y qué es sensible en este dominio.
