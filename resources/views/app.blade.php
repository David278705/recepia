<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('brand.name') }} · {{ config('brand.tagline') }}</title>
    <meta name="description" content="{{ config('brand.name') }} atiende el WhatsApp de tu negocio: responde en segundos, agenda citas contra tu calendario real y te pasa la conversación cuando hace falta una persona. En tu mismo número.">
    <meta name="theme-color" content="#063d37">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta property="og:title" content="{{ config('brand.name') }} · {{ config('brand.tagline') }}">
    <meta property="og:description" content="Pilo atiende el WhatsApp de tu negocio: responde en segundos, agenda citas y escala a una persona cuando hace falta. En tu mismo número.">
    <meta property="og:image" content="/img/logo-round.png">
    <meta property="og:type" content="website">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
