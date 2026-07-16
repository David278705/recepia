<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Límite generoso pero acotado para el webhook público de WhatsApp:
        // agrega el tráfico de todos los negocios de la plataforma, así que
        // no se puede limitar por negocio antes de identificarlo — esto es
        // una defensa contra flood/abuso, no fairness por tenant.
        RateLimiter::for('whatsapp-webhook', function ($request) {
            return Limit::perMinute(config('pilo.whatsapp.webhook_rate_limit'))->by($request->ip());
        });
    }
}
