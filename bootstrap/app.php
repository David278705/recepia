<?php

use App\Http\Middleware\EnsureSubscriptionIsActive;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// API-only app (no Blade login route): never redirect unauthenticated requests
// to a `login` named route — always let them fall through to a JSON 401.
Authenticate::redirectUsing(fn () => null);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás del proxy de Railway (TLS terminado antes de llegar a la
        // app): confiar en X-Forwarded-* para que las URLs salgan en https.
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();
        $middleware->alias([
            'admin' => EnsureUserIsSuperAdmin::class,
            'subscription' => EnsureSubscriptionIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
