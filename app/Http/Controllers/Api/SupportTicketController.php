<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketCreatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Tickets de soporte del usuario autenticado (error, queja o sugerencia).
 * Fuera del paywall: un usuario con problemas de pago también debe poder
 * reportarlos.
 */
class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->withCount('replies')
            ->latest()
            ->get();

        return response()->json(['data' => $tickets]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(SupportTicket::TYPES)],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = $request->user()->supportTickets()->create($data);

        Notification::send(User::superAdmins(), new SupportTicketCreatedNotification($ticket));

        return response()->json(['data' => $ticket], 201);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 404);

        return response()->json(['data' => $supportTicket->load('replies.user:id,name,role')]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 404);

        if ($supportTicket->status === 'cerrado') {
            return response()->json(['message' => 'Este ticket está cerrado. Abre uno nuevo si necesitas ayuda.'], 422);
        }

        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);

        $reply = $supportTicket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        // Una respuesta del usuario vuelve a poner el ticket en la cola del admin.
        $supportTicket->update(['status' => 'abierto']);

        return response()->json(['data' => $reply->load('user:id,name,role')], 201);
    }
}
