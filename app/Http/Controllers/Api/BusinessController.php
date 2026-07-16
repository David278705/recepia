<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        return response()->json(['data' => new BusinessResource($business)]);
    }

    /**
     * Actualiza el negocio del dueño autenticado. La página de Perfil envía
     * solo sus propios campos (todas las reglas son "sometimes").
     */
    public function update(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tone' => ['sometimes', 'required', 'in:formal,cercano'],
            'show_brand' => ['sometimes', 'boolean'],
            'extra_instructions' => ['sometimes', 'nullable', 'string'],
        ]);

        $business->update($data);

        return response()->json(['data' => new BusinessResource($business)]);
    }
}
