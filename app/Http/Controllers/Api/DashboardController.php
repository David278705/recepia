<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        $now = Carbon::now($business->timezone);
        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $monthStart = $now->copy()->startOfMonth();

        return response()->json([
            'data' => [
                'conversations_today' => $business->conversations()
                    ->where('last_activity_at', '>=', $today->copy()->utc())
                    ->count(),

                'appointments_today' => $business->appointments()
                    ->whereIn('status', ['propuesta', 'confirmada'])
                    ->whereBetween('starts_at', [$today->copy()->utc(), $tomorrow->copy()->utc()])
                    ->count(),

                'appointments_tomorrow' => $business->appointments()
                    ->whereIn('status', ['propuesta', 'confirmada'])
                    ->whereBetween('starts_at', [$tomorrow->copy()->utc(), $tomorrow->copy()->addDay()->utc()])
                    ->count(),

                'pending_escalations' => $business->conversations()->where('status', 'escalada')->count(),

                'appointments_booked_by_bot_this_month' => $business->appointments()
                    ->where('origin', 'bot')
                    ->where('created_at', '>=', $monthStart->copy()->utc())
                    ->count(),

                'bot_messages_this_month' => $business->messages()
                    ->where('origin', 'bot')
                    ->where('created_at', '>=', $monthStart->copy()->utc())
                    ->count(),

                'total_contacts' => $business->contacts()->count(),

                'activity_7d' => $this->activityLast7Days($business, $today),
                'todays_appointments' => $this->todaysAppointments($business, $today, $tomorrow),
                'recent_conversations' => $this->recentConversations($business),
            ],
        ]);
    }

    /**
     * Conversaciones con actividad por día, últimos 7 días (hoy incluido),
     * calculadas en la zona horaria del negocio.
     *
     * @return array<int, array{date: string, label: string, conversations: int}>
     */
    protected function activityLast7Days($business, Carbon $today): array
    {
        $windowStart = $today->copy()->subDays(6);

        $messages = $business->messages()
            ->where('created_at', '>=', $windowStart->copy()->utc())
            ->get(['conversation_id', 'created_at']);

        $byDay = $messages
            ->groupBy(fn ($m) => $m->created_at->copy()->setTimezone($today->timezone)->toDateString())
            ->map(fn ($group) => $group->pluck('conversation_id')->unique()->count());

        $dayLabels = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $windowStart->copy()->addDays($i);
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $dayLabels[$day->dayOfWeek],
                'conversations' => $byDay[$day->toDateString()] ?? 0,
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function todaysAppointments($business, Carbon $today, Carbon $tomorrow): array
    {
        return $business->appointments()
            ->with(['contact:id,name,wa_id', 'service:id,name'])
            ->whereIn('status', ['propuesta', 'confirmada'])
            ->whereBetween('starts_at', [$today->copy()->utc(), $tomorrow->copy()->utc()])
            ->orderBy('starts_at')
            ->limit(10)
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'time' => $a->starts_at->copy()->setTimezone($today->timezone)->format('H:i'),
                'service' => $a->service?->name,
                'contact' => $a->contact?->name ?: $a->contact?->wa_id,
                'status' => $a->status,
                'origin' => $a->origin,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentConversations($business): array
    {
        return $business->conversations()
            ->with('contact:id,name,wa_id')
            ->whereNotNull('last_activity_at')
            ->latest('last_activity_at')
            ->limit(5)
            ->get()
            ->map(function (Conversation $c) {
                $lastMessage = $c->messages()->whereNotNull('content')->latest('id')->first();

                return [
                    'id' => $c->id,
                    'contact' => $c->contact?->name ?: $c->contact?->wa_id,
                    'status' => $c->status,
                    'last_activity_at' => $c->last_activity_at?->toIso8601String(),
                    'snippet' => $lastMessage ? mb_strimwidth($lastMessage->content, 0, 80, '…') : null,
                ];
            })
            ->all();
    }
}
