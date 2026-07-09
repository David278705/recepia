<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessHourController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->business->businessHours()->orderBy('day_of_week')->orderBy('opens_at')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $hour = $request->user()->business->businessHours()->create($data);

        return response()->json(['data' => $hour], 201);
    }

    public function update(Request $request, BusinessHour $businessHour): JsonResponse
    {
        $data = $request->validate($this->rules(sometimes: true));

        $businessHour->update($data);

        return response()->json(['data' => $businessHour->fresh()]);
    }

    public function destroy(BusinessHour $businessHour): JsonResponse
    {
        $businessHour->delete();

        return response()->json(['message' => 'Horario eliminado.']);
    }

    protected function rules(bool $sometimes = false): array
    {
        $rule = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return [
            'day_of_week' => $rule(['required', 'integer', 'min:0', 'max:6']),
            'opens_at' => $rule(['required', 'date_format:H:i']),
            'closes_at' => $rule(['required', 'date_format:H:i', 'after:opens_at']),
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
