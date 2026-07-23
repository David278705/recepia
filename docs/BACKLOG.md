# Backlog

Lista priorizada de trabajo. Claude Code toma de arriba hacia abajo cuando no hay nada roto ni riesgos de seguridad pendientes. Puede agregar tareas al final (con diagnóstico), pero no reordenar la prioridad de las que puso el humano.

Formato: `- [ ] (prioridad) Título — contexto breve y criterio de terminado.`

## Prioridad alta

- [ ] (alta) Auditar aislamiento multi-tenant — revisar toda consulta que devuelva datos de cliente y confirmar que está restringida a la cuenta; agregar tests que prueben que la cuenta A no puede leer datos de la B por ninguna ruta.
- [ ] (alta) Cobertura de tests del agente con API mockeada — casos: FAQ respondida, agendamiento exitoso, "no sé" → escala, cliente pide humano → escala, echo del dueño → bot se pausa.
- [ ] (alta) Robustez del webhook — validación de firma, idempotencia por id de mensaje, respuesta rápida + job encolado, manejo de tipos de mensaje no soportados.

## Prioridad media

- [ ] (media) Manejo de errores y reintentos en toda llamada externa (Meta, Anthropic): timeouts, backoff, y que un fallo nunca deje al cliente final sin respuesta.
- [ ] (media) Contadores de consumo: tokens y costo estimado por mensaje y por cuenta, visibles para el super admin.
- [ ] (media) Revisión de rendimiento: N+1 en listados del panel, índices faltantes en columnas de filtrado frecuente.
- [ ] (media) `.env.example` completo y documentado; README con setup local reproducible desde cero.

## Prioridad baja

- [ ] (baja) Pulido de microcopy del panel según `docs/BRAND.md`.
- [ ] (baja) Estados vacíos y mensajes de error amigables en todas las pantallas.

## Agregadas por Claude Code

<!-- Claude Code agrega aquí lo que encuentre, con diagnóstico y criterio de terminado -->
