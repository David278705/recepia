<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBusinessResource;
use App\Models\Business;
use App\Models\User;
use App\Notifications\SubscriptionPriceChangedNotification;
use App\Notifications\WelcomeOwnerNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function index(): JsonResponse
    {
        $monthStart = now()->startOfMonth();

        $businesses = Business::with(['owner', 'whatsappAccount', 'subscription'])
            ->withCount(['conversations as pending_escalations_count' => fn ($q) => $q->where('status', 'escalada')])
            ->withCount(['messages as messages_this_month_count' => fn ($q) => $q->where('created_at', '>=', $monthStart)])
            ->withSum(['messages as cost_this_month' => fn ($q) => $q->where('created_at', '>=', $monthStart)], 'estimated_cost')
            ->latest()
            ->get();

        return response()->json(['data' => AdminBusinessResource::collection($businesses)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge(
            $this->businessRules(),
            $this->ownerRules($request),
            $this->whatsappRules(),
        ));

        $business = DB::transaction(function () use ($data, $request) {
            $ownerId = $request->input('owner_mode') === 'new'
                ? User::create([
                    'name' => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'password' => Hash::make($data['owner_password']),
                ])->id
                : $data['owner_id'];

            $business = Business::create([...$this->onlyBusinessFields($data), 'user_id' => $ownerId]);

            $this->syncWhatsappAccount($business, $data);

            return $business;
        });

        if ($request->input('owner_mode') === 'new') {
            $business->owner?->notify(new WelcomeOwnerNotification($business));
        }

        return response()->json(['data' => new AdminBusinessResource($business->load(['owner', 'whatsappAccount', 'subscription']))], 201);
    }

    public function show(Business $business): JsonResponse
    {
        return response()->json(['data' => new AdminBusinessResource($business->load(['owner', 'whatsappAccount', 'subscription']))]);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate(array_merge(
            $this->businessRules(sometimes: true),
            $this->ownerRules($request, sometimes: true, businessId: $business->id),
            $this->whatsappRules(),
        ));

        $oldPriceCents = $business->monthly_price_cents;

        DB::transaction(function () use ($data, $request, $business) {
            $updates = $this->onlyBusinessFields($data);

            if ($request->input('owner_mode') === 'new') {
                $updates['user_id'] = User::create([
                    'name' => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'password' => Hash::make($data['owner_password']),
                ])->id;
            } elseif ($request->input('owner_mode') === 'existing') {
                $updates['user_id'] = $data['owner_id'];
            }

            $business->update($updates);

            $this->syncWhatsappAccount($business, $data);
        });

        if ($request->input('owner_mode') === 'new') {
            $business->fresh()->owner?->notify(new WelcomeOwnerNotification($business->fresh()));
        }

        $this->handlePriceChange($business->fresh(), $oldPriceCents);

        return response()->json(['data' => new AdminBusinessResource($business->fresh(['owner', 'whatsappAccount', 'subscription']))]);
    }

    /**
     * Un cambio de precio no puede cobrarse por cobro automático sin que el
     * dueño lo acepte (información previa, Ley 1480): si paga con tarjeta, se
     * detiene la renovación automática — conserva el acceso hasta el fin del
     * periodo pagado y debe volver a suscribirse aceptando el precio nuevo.
     * En todos los casos con suscripción vigente se le notifica el cambio.
     */
    protected function handlePriceChange(Business $business, ?int $oldPriceCents): void
    {
        $newPriceCents = $business->monthly_price_cents;

        if ($newPriceCents === $oldPriceCents || $newPriceCents === null || $oldPriceCents === null) {
            return;
        }

        $subscription = $business->subscription;

        if (! $subscription || ! $subscription->grantsAccess()) {
            return;
        }

        $stopAutoRenewal = $subscription->payment_method === 'tarjeta'
            && $subscription->wompi_payment_source_id
            && ! $subscription->cancel_at_period_end;

        if ($stopAutoRenewal) {
            $subscription->update(['cancel_at_period_end' => true, 'cancelled_at' => now()]);
        }

        $business->owner?->notify(new SubscriptionPriceChangedNotification(
            $subscription->fresh(),
            $oldPriceCents,
            $newPriceCents,
            $stopAutoRenewal,
        ));
    }

    public function destroy(Business $business): JsonResponse
    {
        $business->delete();

        return response()->json(['message' => 'Negocio eliminado.']);
    }

    protected function syncWhatsappAccount(Business $business, array $data): void
    {
        if (empty($data['whatsapp_phone_number_id'])) {
            return;
        }

        $updates = [
            'phone_number_id' => $data['whatsapp_phone_number_id'],
            'waba_id' => $data['whatsapp_waba_id'] ?? null,
            'phone_e164' => $data['whatsapp_phone_e164'] ?? null,
            'verify_token' => config('services.whatsapp.verify_token'),
            'mode' => $data['whatsapp_mode'] ?? 'coexistence',
            'connection_status' => 'conectado',
        ];

        // No pisar el token guardado si el admin no envió uno nuevo.
        if (array_key_exists('whatsapp_access_token', $data) && $data['whatsapp_access_token']) {
            $updates['access_token'] = $data['whatsapp_access_token'];
        }

        $business->whatsappAccount()->updateOrCreate(['business_id' => $business->id], $updates);
    }

    protected function businessRules(bool $sometimes = false): array
    {
        $rule = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return [
            'name' => $rule(['required', 'string', 'max:255']),
            'type' => $rule(['required', 'in:barberia,clinica,restaurante,otro']),
            'address' => $rule(['nullable', 'string', 'max:255']),
            'phone' => $rule(['nullable', 'string', 'max:32']),
            'timezone' => $rule(['nullable', 'string', 'max:255']),
            'status' => $rule(['required', 'in:piloto,activo,pausado']),
            // Precio mensual de la suscripción en COP (pesos, no centavos).
            // Vacío = el negocio no paga (piloto/cortesía). Mínimo de Wompi:
            // $1.500 COP por transacción.
            'monthly_price' => $rule(['nullable', 'integer', 'min:1500', 'max:100000000']),
            'tone' => $rule(['required', 'in:formal,cercano']),
            'agent_model' => $rule(['nullable', 'string', 'max:255']),
            'extra_instructions' => $rule(['nullable', 'string']),
        ];
    }

    protected function whatsappRules(): array
    {
        return [
            'whatsapp_phone_number_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_waba_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_phone_e164' => ['sometimes', 'nullable', 'string', 'max:32'],
            'whatsapp_access_token' => ['sometimes', 'nullable', 'string'],
            'whatsapp_mode' => ['sometimes', 'nullable', 'in:coexistence,dedicado'],
        ];
    }

    protected function ownerRules(Request $request, bool $sometimes = false, ?int $businessId = null): array
    {
        $ownerMode = $request->input('owner_mode');

        if (! $sometimes) {
            $request->validate(['owner_mode' => ['required', 'in:new,existing']]);
        }

        if ($ownerMode === 'new') {
            return [
                'owner_name' => ['required', 'string', 'max:255'],
                'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'owner_password' => ['required', 'string', 'min:8'],
            ];
        }

        if ($ownerMode === 'existing') {
            return [
                'owner_id' => [
                    'required',
                    Rule::exists('users', 'id'),
                    function (string $attribute, mixed $value, \Closure $fail) use ($businessId) {
                        $ownerBusinessId = Business::where('user_id', $value)->value('id');

                        if ($ownerBusinessId && $ownerBusinessId !== $businessId) {
                            $fail('Este usuario ya tiene un negocio asignado.');
                        }
                    },
                ],
            ];
        }

        return [];
    }

    protected function onlyBusinessFields(array $data): array
    {
        $fields = collect($data)->only([
            'name', 'type', 'address', 'phone', 'timezone',
            'status', 'tone', 'agent_model', 'extra_instructions',
        ])->all();

        if (array_key_exists('monthly_price', $data)) {
            $fields['monthly_price_cents'] = $data['monthly_price'] !== null
                ? (int) $data['monthly_price'] * 100
                : null;
        }

        return $fields;
    }
}
