<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::with(['contact', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('last_activity_at')
            ->paginate(20);

        return response()->json([
            'data' => ConversationResource::collection($conversations),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->load('contact');
        $messages = $conversation->messages()->latest('id')->limit(50)->get()->reverse()->values();

        return response()->json([
            'data' => new ConversationResource($conversation),
            'messages' => MessageResource::collection($messages),
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:4096']]);

        if (! $conversation->window_expires_at || $conversation->window_expires_at->isPast()) {
            return response()->json([
                'message' => 'La ventana de 24 horas de WhatsApp expiró para esta conversación — el cliente debe escribir primero para poder responderle.',
            ], 422);
        }

        $business = $conversation->business;
        $wamid = null;

        try {
            $response = WhatsAppService::forBusiness($business)->sendText($conversation->contact->wa_id, $data['content']);
            $wamid = $response->json('messages.0.id');
        } catch (Throwable $e) {
            return response()->json(['message' => 'No se pudo enviar el mensaje por WhatsApp: '.$e->getMessage()], 502);
        }

        $message = $conversation->messages()->create([
            'business_id' => $business->id,
            'direction' => 'out',
            'origin' => 'dueno_panel',
            'type' => 'text',
            'content' => $data['content'],
            'wamid' => $wamid,
            'delivery_status' => 'sent',
        ]);

        $conversation->update(['last_activity_at' => now()]);

        return response()->json(['data' => new MessageResource($message)], 201);
    }

    public function takeOver(Conversation $conversation): JsonResponse
    {
        $conversation->update(['status' => 'escalada']);

        return response()->json(['data' => new ConversationResource($conversation)]);
    }

    public function returnToBot(Conversation $conversation): JsonResponse
    {
        $conversation->update(['status' => 'bot_activo', 'bot_paused_until' => null]);

        return response()->json(['data' => new ConversationResource($conversation)]);
    }
}
