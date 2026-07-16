<?php

/*
|--------------------------------------------------------------------------
| Marca — fuente única de verdad
|--------------------------------------------------------------------------
|
| Regla de oro: renombrar la marca debe costar una sola línea. Todo texto
| visible (correos, prompts, UI server-side) sale de aquí o de
| config('app.name'). El espejo frontend vive en resources/js/lib/brand.js
| (los .vue no pueden leer config de PHP) — si cambias algo aquí, cámbialo
| también allá.
|
*/

return [

    'name' => env('APP_NAME', 'Pilo'),

    // Tagline principal (hero / login) y apoyos.
    'tagline' => 'El asistente que nunca deja tu WhatsApp en visto',
    'taglines' => [
        'Tu negocio siempre responde',
        'El empleado más pilo de tu negocio',
    ],

    // Dominio y contacto (placeholders hasta confirmar el dominio final).
    'domain' => 'soypilo.com',
    'support_email' => 'soporte@soypilo.com',
    'whatsapp' => '+57 302 472 0171',
    'whatsapp_link' => 'https://wa.me/573024720171',

    // Rutas legales (relativas al APP_URL).
    'legal' => [
        'terms' => '/terminos',
        'privacy' => '/privacidad',
    ],

    // Redes (completar cuando existan los handles definitivos).
    'social' => [
        'instagram' => null,
        'tiktok' => null,
    ],

];
