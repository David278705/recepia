<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\Wompi\SubscriptionBiller;
use App\Services\Wompi\WompiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de eventos de Wompi (transaction.updated): resuelve los pagos que
 * quedaron en PENDING cuando se creó la transacción y activa o degrada la
 * suscripción según el estado final.
 */
class WompiWebhookController extends Controller
{
    public function handle(Request $request, WompiService $wompi, SubscriptionBiller $biller): JsonResponse
    {
        $payload = $request->json()->all();

        if (! $wompi->isValidEvent($payload)) {
            Log::warning('RecepIA: evento de Wompi con firma inválida, ignorado.');

            return response()->json(['message' => 'Firma inválida.'], 403);
        }

        if (($payload['event'] ?? null) !== 'transaction.updated') {
            return response()->json(['message' => 'Evento ignorado.']);
        }

        $transaction = data_get($payload, 'data.transaction', []);
        $payment = SubscriptionPayment::query()
            ->where('wompi_transaction_id', $transaction['id'] ?? '')
            ->first();

        // Las transacciones creadas por el Widget de checkout no pasan por
        // nuestro backend: la primera noticia es este evento. Se identifican
        // por la referencia ('recepia-sub-{id}-...').
        if (! $payment && ! empty($transaction['id'])) {
            $subscription = $biller->subscriptionFromReference((string) ($transaction['reference'] ?? ''));

            if ($subscription && (int) ($transaction['amount_in_cents'] ?? 0) >= (int) $subscription->price_cents) {
                $payment = SubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'business_id' => $subscription->business_id,
                    'wompi_transaction_id' => $transaction['id'],
                    'amount_cents' => $transaction['amount_in_cents'],
                    'currency' => $transaction['currency'] ?? $subscription->currency,
                    'status' => 'PENDING',
                ]);
            }
        }

        if (! $payment) {
            return response()->json(['message' => 'Transacción desconocida.']);
        }

        $payment->update([
            'status' => $transaction['status'] ?? $payment->status,
            'failure_reason' => $transaction['status_message'] ?? $payment->failure_reason,
        ]);

        $biller->applyPaymentStatus($payment->fresh());

        return response()->json(['message' => 'OK']);
    }
}
