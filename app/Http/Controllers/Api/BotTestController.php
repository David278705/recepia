<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Claude\ReceptionistAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BotTestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string'],
        ]);

        try {
            $reply = (new ReceptionistAgent)->testReply($business, $data['messages']);
        } catch (Throwable $e) {
            return response()->json(['message' => 'El agente no pudo responder: '.$e->getMessage()], 502);
        }

        return response()->json(['data' => ['reply' => $reply]]);
    }
}
