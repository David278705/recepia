<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paywall del panel de owners: sin suscripción activa (cuando el negocio
 * tiene precio configurado) las rutas del panel devuelven 402 y el frontend
 * redirige a la página de suscripción. El super admin y las sesiones de
 * impersonación (soporte) nunca se bloquean.
 */
class EnsureSubscriptionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role === 'super_admin' || ($request->hasSession() && $request->session()->has('impersonator_id'))) {
            return $next($request);
        }

        $business = $user->business;

        // Sin negocio asignado no hay nada que cobrar: las rutas ya responden
        // su propio "tu negocio está siendo configurado".
        if (! $business || $business->hasActiveSubscription()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Necesitas una suscripción activa para usar el panel.',
            'code' => 'subscription_required',
        ], 402);
    }
}
