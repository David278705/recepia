<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Solicitudes capturadas por el bot (pedidos y cotizaciones) del negocio del
 * dueño autenticado.
 */
class CustomerRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        $requests = $business->customerRequests()
            ->with('contact:id,name,wa_id')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByRaw("case status when 'nueva' then 0 when 'atendida' then 1 else 2 end")
            ->latest()
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function updateStatus(Request $request, CustomerRequest $customerRequest): JsonResponse
    {
        abort_unless($customerRequest->business_id === $request->user()->business?->id, 404);

        $data = $request->validate(['status' => ['required', Rule::in(CustomerRequest::STATUSES)]]);

        $customerRequest->update($data);

        return response()->json(['data' => $customerRequest->load('contact:id,name,wa_id')]);
    }
}
