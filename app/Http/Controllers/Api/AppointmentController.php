<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        $weekStart = $request->query('week_start')
            ? Carbon::parse($request->query('week_start'), $business->timezone)->startOfDay()
            : Carbon::now($business->timezone)->startOfWeek();

        $weekEnd = $weekStart->copy()->addDays(7);

        $appointments = Appointment::with(['contact', 'service'])
            ->whereBetween('starts_at', [$weekStart->copy()->utc(), $weekEnd->copy()->utc()])
            ->where('status', '!=', 'cancelada')
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => AppointmentResource::collection($appointments),
            'meta' => ['week_start' => $weekStart->toDateString(), 'week_end' => $weekEnd->copy()->subDay()->toDateString()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        $data = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'contact_mode' => ['required', 'in:new,existing'],
            'contact_id' => ['required_if:contact_mode,existing', 'integer', 'exists:contacts,id'],
            'contact_name' => ['required_if:contact_mode,new', 'string', 'max:255'],
            'contact_wa_id' => ['required_if:contact_mode,new', 'string', 'max:32'],
        ]);

        $contact = $data['contact_mode'] === 'existing'
            ? $business->contacts()->findOrFail($data['contact_id'])
            : Contact::firstOrCreate(
                ['business_id' => $business->id, 'wa_id' => $data['contact_wa_id']],
                ['name' => $data['contact_name']]
            );

        $service = $data['service_id'] ?? null ? $business->services()->find($data['service_id']) : null;
        $startsLocal = Carbon::parse($data['starts_at'], $business->timezone);
        $duration = $service?->duration_minutes ?? 30;

        $appointment = $business->appointments()->create([
            'contact_id' => $contact->id,
            'service_id' => $service?->id,
            'starts_at' => $startsLocal->copy()->utc(),
            'ends_at' => $startsLocal->copy()->addMinutes($duration)->utc(),
            'status' => 'confirmada',
            'origin' => 'panel',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => new AppointmentResource($appointment->load(['contact', 'service']))], 201);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $business = $appointment->business;

        $data = $request->validate([
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'starts_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:propuesta,confirmada,cancelada,completada,no_asistio'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $updates = collect($data)->only(['status', 'notes'])->all();

        if (array_key_exists('service_id', $data)) {
            $updates['service_id'] = $data['service_id'];
        }

        if (isset($data['starts_at'])) {
            $service = $business->services()->find($data['service_id'] ?? $appointment->service_id);
            $duration = $service?->duration_minutes ?? $appointment->starts_at->diffInMinutes($appointment->ends_at);
            $startsLocal = Carbon::parse($data['starts_at'], $business->timezone);

            $updates['starts_at'] = $startsLocal->copy()->utc();
            $updates['ends_at'] = $startsLocal->copy()->addMinutes($duration)->utc();
        }

        $appointment->update($updates);

        return response()->json(['data' => new AppointmentResource($appointment->fresh(['contact', 'service']))]);
    }

    public function cancel(Appointment $appointment): JsonResponse
    {
        $appointment->update(['status' => 'cancelada']);

        return response()->json(['data' => new AppointmentResource($appointment->fresh(['contact', 'service']))]);
    }
}
