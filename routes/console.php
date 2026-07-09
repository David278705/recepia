<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Renovación de suscripciones vencidas (requiere `php artisan schedule:run`
// en el cron del servidor).
Schedule::command('recepia:cobrar-suscripciones')->hourly();

// Recordatorios de renovación por correo: una vez al día, a media mañana
// hora de Colombia.
Schedule::command('recepia:recordatorios-suscripcion')->dailyAt('13:00'); // 8:00 am America/Bogota (servidor en UTC)
