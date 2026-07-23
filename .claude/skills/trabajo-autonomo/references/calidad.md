# Estándares de calidad — Pilo

## Antes de escribir código

1. Lee el código existente alrededor y **sigue sus convenciones**, aunque prefieras otras. La consistencia vale más que tu gusto personal.
2. Busca si ya existe una utilidad o servicio que haga lo que vas a escribir. No dupliques.
3. Usa lo que el framework ya ofrece (Form Requests, Policies, Resources, Jobs, Events, Scopes) antes de inventar mecanismos propios.

## Reglas de código

- **Nombres**: código, clases, métodos, variables y columnas en inglés. Textos de cara al usuario en español.
- **Controladores delgados**: la lógica de negocio va en servicios o acciones, no en el controlador.
- **Nada de valores mágicos**: a config, constantes o enums.
- **Errores explícitos**: nunca silencies excepciones con un catch vacío. Loguea con contexto útil (sin datos sensibles) y falla de forma controlada.
- **Todo lo que llama a una API externa** va en un Job encolado, con timeout, reintentos y manejo de fallo.
- **Consultas**: cuidado con N+1 (usa eager loading); índices en columnas por las que se filtra; nunca traigas colecciones completas para contarlas.
- **Datos sensibles**: tokens y credenciales encriptados en base de datos y jamás en logs, mensajes de error o respuestas de API.
- **Multi-tenant**: toda consulta de datos de cliente debe estar restringida a su cuenta. Si escribes una query que no filtre por cuenta, justifica por qué en un comentario o no la escribas.

## Tests

- Todo bug corregido lleva un test que falla sin el arreglo.
- Toda lógica nueva de negocio lleva test.
- APIs externas siempre mockeadas (`Http::fake`), nunca llamadas reales.
- Prioriza tests que verifiquen **comportamiento**, no implementación.
- Casos que siempre valen la pena: aislamiento entre cuentas, autorización por rol, límites y validaciones, caminos de error, idempotencia.

## Autorrevisión antes de cada commit

Pregúntate y responde honestamente:

1. ¿La suite completa pasa?
2. ¿Un desarrollador nuevo entendería este cambio leyendo solo el diff y el mensaje del commit?
3. ¿Introduje alguna forma de que una cuenta vea datos de otra?
4. ¿Dejé algún secreto, credencial o dato personal en el código, logs o tests?
5. ¿Este cambio es reversible con un solo `git revert`?
6. ¿Rompí alguna funcionalidad existente que no estaba cubierta por tests? (si hay duda: escribe el test antes de continuar)
7. ¿El commit hace UNA cosa?

Si alguna respuesta es mala, arréglalo antes de commitear.

## Qué NO es una mejora

No gastes la sesión en esto:

- Reescribir código que funciona solo por preferencia estética.
- Renombrar variables masivamente sin razón funcional.
- Agregar abstracciones para casos que no existen todavía.
- Micro-optimizaciones sin evidencia de un problema de rendimiento.
- Comentarios que repiten lo que el código ya dice.
- Cambiar convenciones establecidas del repo.
