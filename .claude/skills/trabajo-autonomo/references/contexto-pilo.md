# Contexto del proyecto Pilo

## Qué es

Pilo es una plataforma SaaS multi-tenant para negocios pequeños de Colombia y LatAm (barberías, clínicas estéticas, consultorios, restaurantes, talleres, servicios). Su producto principal es un **recepcionista con IA que atiende el WhatsApp del negocio**: responde preguntas frecuentes, agenda citas y escala al dueño cuando hace falta.

Stack: Laravel + colas + base de datos relacional + Claude API (Anthropic) + WhatsApp Cloud API (Meta).

## Quién lo usa

- **Dueños de negocio**, no técnicos, ocupados, que viven en WhatsApp. Cada texto y cada pantalla deben ser obvios para ellos.
- **Sus clientes finales**, que conversan con el bot por WhatsApp sin saber que hay una plataforma detrás.
- **El super admin** (el dueño de Pilo), que administra cuentas, suscripciones y soporte.

## Identidad de marca

Pilo viene del colombianismo "ser pilo": atento, despierto, diligente. Tono cercano, resuelto, confiable. Español colombiano neutro, tuteo, frases cortas, sin anglicismos innecesarios, nunca infantil ni acartonado. La fuente de verdad del copy es `docs/BRAND.md`.

**Regla de identidad:** con el dueño del negocio, Pilo es Pilo. Con los clientes finales del negocio, el bot se presenta como asistente de ESE negocio, no como Pilo. No la cambies.

## Lo sagrado (no lo toques ni lo debilites)

1. **Aislamiento entre cuentas.** Que los datos de un negocio lleguen a otro —en una consulta, en un panel, o peor, dentro del prompt del modelo— es el fallo más grave posible. Cualquier cosa que refuerce esto es prioridad máxima.
2. **El agente no inventa.** El bot responde solo con la información configurada del negocio (servicios, precios, horarios, FAQs). Si no sabe, escala a un humano. Nunca relajes esta restricción ni agregues fuentes de información al prompt sin que sean del negocio.
3. **Confirmación humana en acciones sensibles.** Datos extraídos de imágenes o audio se confirman antes de guardarse; las acciones que afectan a terceros requieren aprobación.
4. **Validación de firma en webhooks e idempotencia por id de mensaje.** No las quites ni las "simplifiques".
5. **Reglas legales de cobranza** (si el módulo existe): horarios hábiles, topes de frecuencia, lista de exclusión, prohibición de contactar terceros. Van en código, no en prompts.
6. **Tokens y credenciales encriptados**, jamás en logs.

## Particularidades del dominio

- **Ventana de 24 horas de WhatsApp**: responder fuera de ella requiere plantilla aprobada. Cualquier envío proactivo debe considerarlo.
- **Coexistencia**: el dueño sigue usando su app de WhatsApp Business con el mismo número; llegan "echoes" de lo que él escribe. Esos mensajes no son del cliente y no deben alimentar al bot como si lo fueran; cuando el dueño interviene, el bot se pausa en esa conversación.
- **Zona horaria**: los negocios operan en America/Bogotá por defecto. Cuidado con cálculos de disponibilidad y horarios en UTC.
- **Costos**: cada mensaje procesado consume tokens y a veces transcripción. Todo lo que reduzca llamadas innecesarias al modelo o tokens desperdiciados es una mejora real y valiosa.
- **Los dueños abandonan rápido**: un bot que agenda mal una cita o inventa un precio destruye la confianza. La corrección importa más que la cantidad de funciones.
