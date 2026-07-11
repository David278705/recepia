<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Notifications\SupportTicketRepliedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->with('user:id,name,email')
            ->withCount('replies')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByRaw("field(status, 'abierto', 'respondido', 'cerrado')")
            ->latest()
            ->get();

        return response()->json(['data' => $tickets]);
    }

    public function show(SupportTicket $supportTicket): JsonResponse
    {
        return response()->json([
            'data' => $supportTicket->load(['user:id,name,email', 'replies.user:id,name,role']),
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);

        $reply = $supportTicket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        $supportTicket->update(['status' => 'respondido']);

        $supportTicket->user->notify(new SupportTicketRepliedNotification($supportTicket, $data['message']));

        return response()->json(['data' => $reply->load('user:id,name,role')], 201);
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(SupportTicket::STATUSES)]]);

        $supportTicket->update($data);

        return response()->json(['data' => $supportTicket]);
    }
}
