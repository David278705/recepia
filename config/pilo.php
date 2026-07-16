<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp — mensajes entrantes
    |--------------------------------------------------------------------------
    |
    | Comportamiento para tipos de mensaje no soportados en el MVP (audio,
    | imagen, ubicación, etc.): 'reply' responde con un texto fijo cortés;
    | 'escalate' marca la conversación como escalada al dueño.
    |
    */
    'whatsapp' => [
        'unsupported_message_action' => env('WHATSAPP_UNSUPPORTED_MESSAGE_ACTION', 'reply'),
        'unsupported_message_reply' => '¿Me lo puedes escribir en texto? 🙏',

        // Minutos que el bot queda en silencio en una conversación después de
        // que el dueño escribe desde su propia app (echo de coexistence).
        'bot_pause_minutes' => (int) env('WHATSAPP_BOT_PAUSE_MINUTES', 30),

        // Máximo de requests/minuto por IP al webhook — agrega el tráfico de
        // todos los negocios de la plataforma (defensa contra flood/abuso).
        'webhook_rate_limit' => (int) env('WHATSAPP_WEBHOOK_RATE_LIMIT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facturación (suscripciones Wompi)
    |--------------------------------------------------------------------------
    |
    | Días de gracia después de que vence el periodo pagado: el dueño conserva
    | el acceso mientras paga (renovación por transferencia o reintento del
    | cobro a la tarjeta). Al agotarse, la suscripción pasa a 'vencida' y el
    | panel se bloquea.
    |
    */
    'billing' => [
        'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agente (Claude)
    |--------------------------------------------------------------------------
    */
    'agent' => [
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 512),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 20), // segundos
        'max_tool_rounds' => 5,
        'context_messages' => 20,

        // Precio estimado en USD por millón de tokens, para el costo por
        // mensaje guardado en messages/agent_logs. Ajustar si Anthropic
        // cambia sus tarifas — ver https://www.anthropic.com/pricing.
        'pricing' => [
            'claude-haiku-4-5-20251001' => ['input' => 1.0, 'output' => 5.0],
            'claude-haiku-4-5' => ['input' => 1.0, 'output' => 5.0],
            'claude-sonnet-5' => ['input' => 3.0, 'output' => 15.0],
        ],
        'default_pricing' => ['input' => 3.0, 'output' => 15.0],
    ],

];
