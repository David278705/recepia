<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailableOwnersController extends Controller
{
    /**
     * Usuarios sin negocio asignado (para el selector "dueño existente" del
     * formulario de alta/edición). Si se está editando un negocio, incluye
     * también a su dueño actual para que siga apareciendo seleccionado.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::where('role', 'owner')
            ->where(function ($query) use ($request) {
                $query->whereDoesntHave('business');

                if ($businessId = $request->integer('exclude_business')) {
                    $query->orWhereHas('business', fn ($q) => $q->whereKey($businessId));
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }
}
