<?php

namespace App\Services\Wompi;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\Admin\AdminSubscriptionAlert;
use App\Notifications\PaymentReceiptNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Orquesta el ciclo de vida de la suscripción sobre Wompi: alta con tarjeta
 * tokenizada, cobro del periodo y aplicación del resultado (aprobado,
 * rechazado o pendiente de webhook).
 */
class SubscriptionBiller
{
    public function __construct(protected WompiService $wompi) {}

    /**
     * Alta (o reactivación) de la suscripción de un negocio con un token de
     * tarjeta nuevo: crea la fuente de pago y cobra el primer periodo.
     */
    public function subscribe(Business $business, string $cardToken): Subscription
    {
        if (! $business->requiresSubscription()) {
            throw new RuntimeException('Este negocio no tiene un precio de suscripción configurado.');
        }

        $email = $business->owner?->email;

        if (! $email) {
            throw new RuntimeException('El negocio no tiene un dueño con correo para facturar.');
        }

        $acceptance = $this->wompi->acceptanceToken();
        $source = $this->wompi->createPaymentSource($cardToken, $email, $acceptance['acceptance_token']);

        $subscription = $business->subscription()->firstOrNew([]);
        $subscription->fill([
            'business_id' => $business->id,
            'status' => $subscription->grantsAccess() ? $subscription->status : 'pendiente',
            'payment_method' => 'tarjeta',
            'price_cents' => $business->monthly_price_cents,
            'currency' => 'COP',
            'wompi_payment_source_id' => $source['id'],
            'card_brand' => data_get($source, 'public_data.brand'),
            'card_last_four' => data_get($source, 'public_data.last_four'),
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
        ]);
        $subscription->save();

        // Si aún tiene periodo pagado vigente (solo cambió de tarjeta), no se
        // cobra de nuevo; el siguiente cobro usará la fuente nueva.
        if (! $subscription->grantsAccess()) {
            $this->charge($subscription);
        }

        return $subscription->fresh();
    }

    /**
     * Inicia un pago del periodo con un método directo de la API de Wompi
     * (nequi / daviplata / pse): deja la suscripción lista, crea la
     * transacción y devuelve el pago registrado más la URL a la que hay que
     * llevar al cliente (banco para PSE, OTP para DaviPlata; Nequi no
     * redirige — el cliente confirma en su app). La confirmación final llega
     * por webhook o polling.
     *
     * @param  array<string, mixed>  $input  Campos ya validados según el método
     * @return array{subscription: Subscription, payment: SubscriptionPayment, redirect_url: ?string}
     */
    public function startDirectPayment(Business $business, string $method, array $input, string $returnUrl): array
    {
        if (! $business->requiresSubscription()) {
            throw new RuntimeException('Este negocio no tiene un precio de suscripción configurado.');
        }

        $email = $business->owner?->email;

        if (! $email) {
            throw new RuntimeException('El negocio no tiene un dueño con correo para facturar.');
        }

        $subscription = $business->subscription()->firstOrNew([]);
        $subscription->fill([
            'business_id' => $business->id,
            'status' => $subscription->grantsAccess() ? $subscription->status : 'pendiente',
            // Estos métodos se pagan manualmente mes a mes; solo una tarjeta
            // guardada mantiene el cobro automático.
            'payment_method' => $subscription->wompi_payment_source_id ? $subscription->payment_method : 'transferencia',
            'price_cents' => $business->monthly_price_cents,
            'currency' => 'COP',
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
        ]);
        $subscription->save();

        $amount = (int) $subscription->price_cents;
        $reference = 'pilo-sub-'.$subscription->id.'-'.Str::lower(Str::random(12));
        $acceptance = $this->wompi->acceptanceToken();

        [$paymentMethod, $customerData, $urlPath] = match ($method) {
            'nequi' => [
                ['type' => 'NEQUI', 'phone_number' => $input['phone']],
                null,
                null,
            ],
            'daviplata' => [
                [
                    'type' => 'DAVIPLATA',
                    'user_legal_id_type' => $input['legal_id_type'],
                    'user_legal_id' => $input['legal_id'],
                    // DaviPlata acepta máximo 30 caracteres aquí.
                    'payment_description' => mb_strimwidth('Suscripción Pilo', 0, 30),
                ],
                null,
                'payment_method.extra.url',
            ],
            'pse' => [
                [
                    'type' => 'PSE',
                    'user_type' => (int) $input['user_type'],
                    'user_legal_id_type' => $input['legal_id_type'],
                    'user_legal_id' => $input['legal_id'],
                    'financial_institution_code' => (string) $input['financial_institution_code'],
                    'payment_description' => mb_strimwidth('Suscripción Pilo — '.$business->name, 0, 64),
                ],
                ['phone_number' => $input['phone'], 'full_name' => $input['full_name']],
                'payment_method.extra.async_payment_url',
            ],
            default => throw new RuntimeException("Método de pago no soportado: {$method}."),
        };

        $transaction = $this->wompi->createPaymentTransaction(
            $paymentMethod,
            $amount,
            $subscription->currency,
            $email,
            $reference,
            $acceptance['acceptance_token'],
            $this->isLocalUrl($returnUrl) ? null : $returnUrl,
            $customerData,
        );

        $payment = $subscription->payments()->create([
            'business_id' => $business->id,
            'wompi_transaction_id' => $transaction['id'],
            'amount_cents' => $amount,
            'currency' => $subscription->currency,
            'status' => $transaction['status'] ?? 'PENDING',
        ]);

        $redirectUrl = null;

        if ($urlPath) {
            $transaction = $this->wompi->waitForTransactionField($transaction['id'], $urlPath);
            $redirectUrl = data_get($transaction, $urlPath);
        }

        // Aplica de una vez si Wompi ya la resolvió (p. ej. rechazo inmediato).
        $payment->update([
            'status' => $transaction['status'] ?? $payment->status,
            'failure_reason' => $transaction['status_message'] ?? null,
        ]);
        $this->applyPaymentStatus($payment->fresh());

        return [
            'subscription' => $subscription->fresh(),
            'payment' => $payment->fresh(),
            'redirect_url' => $redirectUrl,
        ];
    }

    protected function isLocalUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === ''
            || in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.localhost');
    }

    /**
     * Registra una transacción creada por el Widget (o por redirección) y le
     * aplica su estado a la suscripción. Valida que la referencia sea de la
     * suscripción del negocio y que el monto coincida con el plan.
     */
    public function confirmTransaction(Business $business, string $transactionId): SubscriptionPayment
    {
        $transaction = $this->wompi->getTransaction($transactionId);

        $subscription = $this->subscriptionFromReference((string) ($transaction['reference'] ?? ''));

        if (! $subscription || $subscription->business_id !== $business->id) {
            throw new RuntimeException('La transacción no corresponde a la suscripción de este negocio.');
        }

        if ((int) ($transaction['amount_in_cents'] ?? 0) < (int) $subscription->price_cents) {
            throw new RuntimeException('El monto de la transacción no coincide con el plan.');
        }

        $payment = SubscriptionPayment::query()->firstOrCreate(
            ['wompi_transaction_id' => $transaction['id']],
            [
                'subscription_id' => $subscription->id,
                'business_id' => $subscription->business_id,
                'amount_cents' => $transaction['amount_in_cents'],
                'currency' => $transaction['currency'] ?? $subscription->currency,
                'status' => 'PENDING',
            ],
        );

        $payment->update([
            'status' => $transaction['status'] ?? 'PENDING',
            'failure_reason' => $transaction['status_message'] ?? null,
        ]);

        $this->applyPaymentStatus($payment->fresh());

        return $payment->fresh();
    }

    /**
     * Resuelve la suscripción a la que pertenece una referencia nuestra
     * ('pilo-sub-{id}-...'). Devuelve null para referencias ajenas.
     */
    public function subscriptionFromReference(string $reference): ?Subscription
    {
        // Acepta también el prefijo anterior al rebrand (recepia-sub-) para no
        // dejar huérfanas transacciones creadas antes del cambio de marca.
        if (! preg_match('/^(?:pilo|recepia)-sub-(\d+)-/', $reference, $matches)) {
            return null;
        }

        return Subscription::query()->withoutGlobalScopes()->find((int) $matches[1]);
    }

    /**
     * Consulta en Wompi el estado real de los pagos PENDING de una
     * suscripción y los aplica. Lo usa el polling del frontend como respaldo
     * del webhook (p. ej. en local, donde Wompi no puede llamarnos).
     */
    public function syncPendingPayments(Subscription $subscription): void
    {
        $pending = $subscription->payments()->where('status', 'PENDING')->get();

        foreach ($pending as $payment) {
            try {
                $transaction = $this->wompi->getTransaction($payment->wompi_transaction_id);
            } catch (Throwable) {
                continue;
            }

            if (($transaction['status'] ?? 'PENDING') === 'PENDING') {
                continue;
            }

            $payment->update([
                'status' => $transaction['status'],
                'failure_reason' => $transaction['status_message'] ?? null,
            ]);

            $this->applyPaymentStatus($payment->fresh());
        }
    }

    /**
     * Cobra un periodo mensual a la fuente de pago de la suscripción y aplica
     * el resultado. Devuelve el pago registrado.
     */
    public function charge(Subscription $subscription): SubscriptionPayment
    {
        $business = $subscription->business;

        if (! $subscription->wompi_payment_source_id) {
            throw new RuntimeException('La suscripción no tiene una fuente de pago.');
        }

        $reference = 'pilo-sub-'.$subscription->id.'-'.Str::lower(Str::random(12));

        $transaction = $this->wompi->chargePaymentSource(
            $subscription->wompi_payment_source_id,
            (int) $subscription->price_cents,
            $subscription->currency,
            $business->owner->email,
            $reference,
        );

        $transaction = $this->wompi->waitForTransaction($transaction['id']);

        $payment = $subscription->payments()->create([
            'business_id' => $business->id,
            'wompi_transaction_id' => $transaction['id'],
            'amount_cents' => $subscription->price_cents,
            'currency' => $subscription->currency,
            'status' => $transaction['status'],
            'failure_reason' => $transaction['status_message'] ?? null,
        ]);

        $this->applyPaymentStatus($payment);

        return $payment->fresh();
    }

    /**
     * Aplica a la suscripción el estado final de un pago (lo llama tanto el
     * cobro directo como el webhook de eventos de Wompi).
     */
    public function applyPaymentStatus(SubscriptionPayment $payment): void
    {
        $subscription = $payment->subscription;

        if ($payment->status === 'APPROVED') {
            // Idempotencia: el mismo pago puede llegar por varias vías
            // (webhook reenviado, polling, confirmación manual). paid_at
            // marca que ya extendió el periodo — nunca extender dos veces.
            if ($payment->paid_at) {
                return;
            }

            $payment->update(['paid_at' => now()]);

            // Primera activación = nunca ha tenido un periodo pagado.
            $wasActivation = $subscription->current_period_ends_at === null;

            // El nuevo periodo arranca donde termina el vigente (renovación
            // anticipada) o ahora (alta / reactivación tras vencerse).
            $base = $subscription->current_period_ends_at?->isFuture()
                ? $subscription->current_period_ends_at
                : now();

            $subscription->update([
                'status' => 'activa',
                'current_period_ends_at' => $base->copy()->addMonth(),
            ]);

            $subscription->refresh();
            $business = $subscription->business;

            $business?->owner?->notify(new PaymentReceiptNotification($payment->fresh(), $wasActivation));

            if ($business) {
                Notification::send(User::superAdmins(), new AdminSubscriptionAlert('pago_recibido', $business, [
                    'amount_cents' => (int) $payment->amount_cents,
                    'activation' => $wasActivation,
                    'method' => $subscription->payment_method === 'tarjeta' ? 'cobro automático a tarjeta' : 'pago manual',
                    'period_ends_at' => $subscription->current_period_ends_at?->translatedFormat('j \d\e F \d\e Y'),
                ]));
            }

            return;
        }

        if (in_array($payment->status, ['DECLINED', 'VOIDED', 'ERROR'], true)) {
            // Solo degrada si no queda periodo pagado vigente.
            if (! $subscription->grantsAccess()) {
                $subscription->update(['status' => 'vencida']);
            }
        }
    }
}
