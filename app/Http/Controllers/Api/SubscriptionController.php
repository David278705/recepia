<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Admin\AdminSubscriptionAlert;
use App\Notifications\CancellationScheduledNotification;
use App\Services\Wompi\SubscriptionBiller;
use App\Services\Wompi\WompiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SubscriptionController extends Controller
{
    public function show(Request $request, SubscriptionBiller $biller): JsonResponse
    {
        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        // Respaldo del webhook: si hay pagos por transferencia en proceso,
        // consulta su estado real en Wompi (clave en local/desarrollo, donde
        // Wompi no puede llamarnos de vuelta).
        if ($business->subscription?->payments()->where('status', 'PENDING')->exists()) {
            try {
                $biller->syncPendingPayments($business->subscription);
                $business->refresh();
            } catch (Throwable) {
                // Si Wompi no responde, el payload sale con el último estado
                // conocido y el frontend seguirá reintentando.
            }
        }

        return response()->json(['data' => $this->payload($business)]);
    }

    /**
     * Alta o reactivación pagando con tarjeta (cobro mensual automático).
     * La tarjeta se tokeniza aquí en el backend contra Wompi — el navegador
     * nunca habla con Wompi, así que no hay CORS posible.
     */
    public function subscribe(Request $request, SubscriptionBiller $biller, WompiService $wompi): JsonResponse
    {
        $data = $request->validate([
            'card_token' => ['required_without:card_number', 'string', 'max:255'],
            'card_number' => ['required_without:card_token', 'string', 'regex:/^\d{13,19}$/'],
            'cvc' => ['required_with:card_number', 'string', 'regex:/^\d{3,4}$/'],
            'exp_month' => ['required_with:card_number', 'string', 'regex:/^\d{1,2}$/'],
            'exp_year' => ['required_with:card_number', 'string', 'regex:/^\d{2}$/'],
            'card_holder' => ['required_with:card_number', 'string', 'min:3', 'max:120'],
        ]);

        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        if (! $business->requiresSubscription()) {
            return response()->json(['message' => 'Tu negocio no requiere suscripción.'], 422);
        }

        if (! $wompi->hasIntegritySecret()) {
            Log::error('RecepIA: WOMPI_INTEGRITY_SECRET no está configurado; Wompi rechazará el cobro (firma de integridad requerida).');

            return response()->json([
                'message' => 'Los pagos no están disponibles en este momento. Por favor contáctanos (falta configurar la llave de integridad de Wompi).',
            ], 422);
        }

        try {
            $cardToken = $data['card_token'] ?? $wompi->tokenizeCard([
                'number' => $data['card_number'],
                'cvc' => $data['cvc'],
                'exp_month' => $data['exp_month'],
                'exp_year' => $data['exp_year'],
                'card_holder' => $data['card_holder'],
            ])['id'];

            $subscription = $biller->subscribe($business, $cardToken);
        } catch (Throwable $e) {
            Log::error('RecepIA: fallo al crear la suscripción en Wompi.', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No pudimos procesar el pago. Verifica los datos de tu tarjeta e inténtalo de nuevo.',
            ], 422);
        }

        $business->refresh();

        if ($subscription->status === 'vencida') {
            return response()->json([
                'message' => 'Tu banco rechazó el cobro. Intenta con otra tarjeta.',
                'data' => $this->payload($business),
            ], 422);
        }

        return response()->json(['data' => $this->payload($business)]);
    }

    /**
     * Bancos disponibles para PSE.
     */
    public function banks(WompiService $wompi): JsonResponse
    {
        try {
            return response()->json(['data' => $wompi->financialInstitutions()]);
        } catch (Throwable $e) {
            Log::error('RecepIA: no se pudo obtener la lista de bancos PSE de Wompi.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'No pudimos cargar la lista de bancos. Inténtalo de nuevo.'], 422);
        }
    }

    /**
     * Pago del periodo con Nequi, DaviPlata o PSE (creado por nuestra API,
     * sin el widget de Wompi). Devuelve la URL de redirección cuando el
     * método la requiere (banco PSE, OTP DaviPlata).
     */
    public function pay(Request $request, SubscriptionBiller $biller, WompiService $wompi): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'in:nequi,daviplata,pse'],
            'phone' => ['required_if:method,nequi,pse', 'nullable', 'string', 'regex:/^3\d{9}$/'],
            'legal_id_type' => ['required_if:method,daviplata,pse', 'nullable', 'in:CC,CE,NIT'],
            'legal_id' => ['required_if:method,daviplata,pse', 'nullable', 'string', 'regex:/^\d{5,15}$/'],
            'user_type' => ['required_if:method,pse', 'nullable', 'in:0,1'],
            'financial_institution_code' => ['required_if:method,pse', 'nullable', 'string', 'max:10'],
            'full_name' => ['required_if:method,pse', 'nullable', 'string', 'min:3', 'max:120'],
        ]);

        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        if (! $business->requiresSubscription()) {
            return response()->json(['message' => 'Tu negocio no requiere suscripción.'], 422);
        }

        if (! $wompi->hasIntegritySecret()) {
            Log::error('RecepIA: WOMPI_INTEGRITY_SECRET no está configurado; Wompi rechazará la transacción.');

            return response()->json([
                'message' => 'Los pagos no están disponibles en este momento. Por favor contáctanos (falta configurar la llave de integridad de Wompi).',
            ], 422);
        }

        // Evita pagos dobles, pero sin dejar al dueño bloqueado si una
        // transacción vieja se quedó en PENDING (p. ej. un PSE abandonado).
        $freshPending = $business->subscription?->payments()
            ->where('status', 'PENDING')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->exists();

        if ($freshPending) {
            return response()->json([
                'message' => 'Ya tienes un pago en proceso. Espera unos minutos a que se confirme antes de iniciar otro.',
            ], 422);
        }

        try {
            $result = $biller->startDirectPayment($business, $data['method'], $data, url('/subscription'));
        } catch (Throwable $e) {
            Log::error('RecepIA: fallo al iniciar el pago directo en Wompi.', [
                'business_id' => $business->id,
                'method' => $data['method'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No pudimos iniciar el pago. Revisa los datos e inténtalo de nuevo.',
            ], 422);
        }

        $business->refresh();

        if ($result['payment']->status === 'DECLINED' || $result['payment']->status === 'ERROR') {
            return response()->json([
                'message' => 'El pago fue rechazado. Inténtalo de nuevo o usa otro método.',
                'data' => $this->payload($business),
            ], 422);
        }

        return response()->json([
            'data' => $this->payload($business),
            'redirect_url' => $result['redirect_url'],
        ]);
    }

    /**
     * Elimina la tarjeta guardada para cobro automático: la suscripción pasa
     * a pago manual mes a mes (con los días de gracia para renovar).
     */
    public function deleteCard(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        $subscription = $business?->subscription;

        if (! $subscription || ! $subscription->wompi_payment_source_id) {
            return response()->json(['message' => 'No tienes una tarjeta guardada.'], 422);
        }

        $subscription->update([
            'wompi_payment_source_id' => null,
            'card_brand' => null,
            'card_last_four' => null,
            'payment_method' => 'transferencia',
        ]);

        return response()->json(['data' => $this->payload($business->fresh())]);
    }

    /**
     * Confirma una transacción creada por el Widget (callback del modal o
     * redirección con ?id=...): consulta su estado real en Wompi y lo aplica.
     */
    public function confirm(Request $request, SubscriptionBiller $biller): JsonResponse
    {
        $data = $request->validate(['transaction_id' => ['required', 'string', 'max:255']]);

        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'Tu negocio aún no ha sido configurado.'], 404);
        }

        try {
            $biller->confirmTransaction($business, $data['transaction_id']);
        } catch (Throwable $e) {
            Log::error('RecepIA: fallo al confirmar la transacción del widget de Wompi.', [
                'business_id' => $business->id,
                'transaction_id' => $data['transaction_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No pudimos verificar el pago todavía. Si ya pagaste, se confirmará automáticamente en unos minutos.',
                'data' => $this->payload($business->fresh()),
            ], 422);
        }

        return response()->json(['data' => $this->payload($business->fresh())]);
    }

    /**
     * Cancela al final del periodo pagado: el acceso se mantiene hasta esa
     * fecha y no se vuelve a cobrar.
     */
    public function cancel(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        $subscription = $business?->subscription;

        if (! $subscription || ! $subscription->grantsAccess()) {
            return response()->json(['message' => 'No tienes una suscripción activa que cancelar.'], 422);
        }

        $subscription->update(['cancel_at_period_end' => true, 'cancelled_at' => now()]);

        $business->owner?->notify(new CancellationScheduledNotification($subscription->fresh()));
        Notification::send(User::superAdmins(), new AdminSubscriptionAlert('cancelacion_programada', $business, [
            'period_ends_at' => $subscription->current_period_ends_at?->translatedFormat('j \d\e F \d\e Y'),
        ]));

        return response()->json(['data' => $this->payload($business->fresh())]);
    }

    /**
     * Revierte una cancelación programada mientras el periodo siga vigente.
     */
    public function resume(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        $subscription = $business?->subscription;

        if (! $subscription || ! $subscription->grantsAccess() || ! $subscription->cancel_at_period_end) {
            return response()->json(['message' => 'No hay una cancelación programada que revertir.'], 422);
        }

        $subscription->update(['cancel_at_period_end' => false, 'cancelled_at' => null]);

        Notification::send(User::superAdmins(), new AdminSubscriptionAlert('cancelacion_reanudada', $business));

        return response()->json(['data' => $this->payload($business->fresh())]);
    }

    protected function payload(Business $business): array
    {
        $subscription = $business->subscription;

        return [
            'business_name' => $business->name,
            'requires_subscription' => $business->requiresSubscription(),
            'has_access' => $business->hasActiveSubscription(),
            'price_cents' => $business->monthly_price_cents,
            'currency' => 'COP',
            'grace_days' => Subscription::graceDays(),
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'payment_method' => $subscription->payment_method,
                'card_brand' => $subscription->card_brand,
                'card_last_four' => $subscription->card_last_four,
                'current_period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
                'access_until' => $subscription->accessUntil()?->toIso8601String(),
                'payment_due' => $subscription->isPaymentDue(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'has_pending_payment' => $subscription->payments()->where('status', 'PENDING')->exists(),
            ] : null,
            'payments' => $subscription
                ? $subscription->payments()->latest()->limit(12)->get()->map(fn ($p) => [
                    'id' => $p->id,
                    'amount_cents' => $p->amount_cents,
                    'currency' => $p->currency,
                    'status' => $p->status,
                    'failure_reason' => $p->failure_reason,
                    'created_at' => $p->created_at->toIso8601String(),
                ])->all()
                : [],
        ];
    }
}
