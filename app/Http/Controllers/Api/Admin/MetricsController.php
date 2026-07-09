<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class MetricsController extends Controller
{
    public function show(): JsonResponse
    {
        $monthStart = Carbon::now()->startOfMonth();

        // Agregados del mes de los mensajes generados por el bot (únicos con
        // tokens/costo), agrupados por negocio.
        $botUsage = Message::query()
            ->withoutGlobalScopes()
            ->where('origin', 'bot')
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('business_id, COUNT(*) as bot_messages, COALESCE(SUM(tokens_used), 0) as tokens, COALESCE(SUM(estimated_cost), 0) as cost')
            ->groupBy('business_id')
            ->get()
            ->keyBy('business_id');

        $businesses = Business::query()
            ->withCount([
                'conversations as conversations_this_month' => fn ($q) => $q->where('last_activity_at', '>=', $monthStart),
                'appointments as bot_appointments_this_month' => fn ($q) => $q->where('origin', 'bot')->where('created_at', '>=', $monthStart),
                'conversations as pending_escalations' => fn ($q) => $q->where('status', 'escalada'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Business $business) use ($botUsage) {
                $usage = $botUsage->get($business->id);

                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'status' => $business->status,
                    'conversations_this_month' => $business->conversations_this_month,
                    'bot_messages_this_month' => (int) ($usage->bot_messages ?? 0),
                    'tokens_this_month' => (int) ($usage->tokens ?? 0),
                    'estimated_cost_this_month' => round((float) ($usage->cost ?? 0), 4),
                    'bot_appointments_this_month' => $business->bot_appointments_this_month,
                    'pending_escalations' => $business->pending_escalations,
                ];
            });

        return response()->json([
            'data' => [
                'active_businesses' => $businesses->where('status', 'activo')->count(),
                'total_businesses' => $businesses->count(),
                'conversations_this_month' => $businesses->sum('conversations_this_month'),
                'bot_messages_this_month' => $businesses->sum('bot_messages_this_month'),
                'tokens_this_month' => $businesses->sum('tokens_this_month'),
                'estimated_cost_this_month' => round($businesses->sum('estimated_cost_this_month'), 4),
                'bot_appointments_this_month' => $businesses->sum('bot_appointments_this_month'),
                'pending_escalations' => $businesses->sum('pending_escalations'),
                'businesses' => $businesses->values(),
            ],
        ]);
    }
}
