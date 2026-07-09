<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->business->faqs()->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $faq = $request->user()->business->faqs()->create($data);

        return response()->json(['data' => $faq], 201);
    }

    public function update(Request $request, Faq $faq): JsonResponse
    {
        $data = $request->validate($this->rules(sometimes: true));

        $faq->update($data);

        return response()->json(['data' => $faq->fresh()]);
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json(['message' => 'Pregunta frecuente eliminada.']);
    }

    protected function rules(bool $sometimes = false): array
    {
        $rule = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return [
            'question' => $rule(['required', 'string', 'max:500']),
            'answer' => $rule(['required', 'string']),
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
