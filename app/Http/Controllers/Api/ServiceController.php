<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->business->services()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $service = $request->user()->business->services()->create($data);

        return response()->json(['data' => $service], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate($this->rules(sometimes: true));

        $service->update($data);

        return response()->json(['data' => $service->fresh()]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(['message' => 'Servicio eliminado.']);
    }

    protected function rules(bool $sometimes = false): array
    {
        $rule = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return [
            'name' => $rule(['required', 'string', 'max:255']),
            'description' => $rule(['nullable', 'string']),
            'duration_minutes' => $rule(['required', 'integer', 'min:5']),
            'price' => $rule(['nullable', 'numeric', 'min:0']),
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
