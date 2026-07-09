<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Impersonación para soporte: el super_admin entra a la cuenta del dueño de
 * un negocio sin necesitar su contraseña, y puede volver a su propia sesión.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, Business $business): JsonResponse
    {
        if (! $business->owner) {
            return response()->json(['message' => 'Este negocio no tiene un dueño asignado.'], 422);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::guard('web')->login($business->owner);
        $request->session()->regenerate();

        return response()->json(['user' => $business->owner]);
    }

    public function stop(Request $request): JsonResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if (! $impersonatorId || ! ($admin = User::find($impersonatorId))) {
            return response()->json(['message' => 'No hay una sesión de impersonación activa.'], 400);
        }

        $request->session()->forget('impersonator_id');
        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return response()->json(['user' => $admin]);
    }
}
