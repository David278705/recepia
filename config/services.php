<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
    ],

    'whatsapp' => [
        // Handshake de verificación del webhook (GET) — se configura una sola
        // vez en el panel de Meta para la app completa.
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        // App Secret de la app de Meta — firma los payloads del webhook
        // (header X-Hub-Signature-256). No es por negocio.
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    ],

    'meta' => [
        // App de Meta para el Embedded Signup (Facebook Login for Business).
        'app_id' => env('META_APP_ID'),
        // Reutiliza el app secret del webhook si no se define uno propio.
        'app_secret' => env('META_APP_SECRET', env('WHATSAPP_APP_SECRET')),
        'graph_version' => env('META_GRAPH_VERSION', 'v23.0'),
        // config_id de la configuración de Facebook Login for Business.
        'es_config_id' => env('META_ES_CONFIG_ID'),
        // System user token del portafolio propio — necesario SOLO para el
        // alta por el link genérico alojado por Meta (PARTNER_ADDED).
        'system_user_token' => env('META_SYSTEM_USER_TOKEN'),
    ],

    'wompi' => [
        // Sandbox: https://sandbox.wompi.co/v1 — Producción: https://production.wompi.co/v1
        'base_url' => env('WOMPI_BASE_URL', 'https://sandbox.wompi.co/v1'),
        'public_key' => env('WOMPI_PUBLIC_KEY'),
        'private_key' => env('WOMPI_PRIVATE_KEY'),
        // Secreto de Eventos (webhook).
        'events_secret' => env('WOMPI_EVENTS_SECRET'),
        // Secreto de Integridad: firma cada transacción (obligatorio cuando
        // la cuenta lo tiene activado, y siempre para el Widget).
        'integrity_secret' => env('WOMPI_INTEGRITY_SECRET'),
    ],

];
