<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * No hay autoservicio de registro: el alta de un dueño de negocio (cuenta
     * + negocio) la hace el administrador desde /admin.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user()->toArray();
        $user['impersonating'] = false;
        $user['subscription_required'] = $this->subscriptionRequired($request);

        return response()->json(['user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->toArray();
        $user['impersonating'] = $request->session()->has('impersonator_id');
        $user['subscription_required'] = $this->subscriptionRequired($request);

        return response()->json(['user' => $user]);
    }

    /**
     * true cuando un owner debe pasar por el paywall de suscripción antes de
     * usar el panel (el frontend lo usa para redirigir a /subscription).
     */
    protected function subscriptionRequired(Request $request): bool
    {
        $user = $request->user();

        if ($user->role === 'super_admin' || ($request->hasSession() && $request->session()->has('impersonator_id'))) {
            return false;
        }

        $business = $user->business;

        return $business !== null && ! $business->hasActiveSubscription();
    }
}
