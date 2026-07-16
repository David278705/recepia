<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Admin\AdminSubscriptionAlert;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionEndedNotification;
use App\Notifications\SubscriptionExpiredNotification;
use App\Services\Wompi\SubscriptionBiller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ChargeDueSubscriptions extends Command
{
    protected $signature = 'pilo:cobrar-suscripciones';

    protected $description = 'Renueva las suscripciones cuyo periodo venció: cobra a la fuente de pago o las marca vencidas/canceladas.';

    public function handle(SubscriptionBiller $biller): int
    {
        $due = Subscription::query()
            ->where('status', 'activa')
            ->where('current_period_ends_at', '<=', now())
            ->with('business.owner')
            ->get();

        foreach ($due as $subscription) {
            $business = $subscription->business;

            if ($subscription->cancel_at_period_end) {
                $subscription->update(['status' => 'cancelada']);
                $business?->owner?->notify(new SubscriptionEndedNotification($subscription));
                Notification::send(User::superAdmins(), new AdminSubscriptionAlert('suscripcion_finalizada', $business));
                $this->info("Suscripción #{$subscription->id} ({$business->name}): cancelada al fin del periodo.");

                continue;
            }

            // Tarjeta: cobro automático (se reintenta cada corrida durante la
            // gracia). Transferencia: paga el dueño desde el panel, aquí solo
            // se vigila el plazo.
            if ($subscription->payment_method === 'tarjeta' && $subscription->wompi_payment_source_id) {
                try {
                    $payment = $biller->charge($subscription);
                    $this->info("Suscripción #{$subscription->id} ({$business->name}): cobro {$payment->status}.");

                    // Avisar el rechazo una sola vez por ciclo, no en cada
                    // reintento horario: solo cuando es el primer intento
                    // fallido desde que venció el periodo.
                    $failedAttempts = $subscription->payments()
                        ->where('created_at', '>=', $subscription->current_period_ends_at)
                        ->whereIn('status', ['DECLINED', 'VOIDED', 'ERROR'])
                        ->count();

                    if ($payment->status !== 'APPROVED' && $payment->status !== 'PENDING' && $failedAttempts === 1) {
                        $business?->owner?->notify(new PaymentFailedNotification($subscription->fresh(), $payment->failure_reason));
                        Notification::send(User::superAdmins(), new AdminSubscriptionAlert('cobro_fallido', $business, [
                            'amount_cents' => (int) $payment->amount_cents,
                            'reason' => $payment->failure_reason,
                        ]));
                    }
                } catch (Throwable $e) {
                    // No degradar por un error de red/API: se reintenta en la
                    // próxima corrida dentro de la gracia.
                    $this->error("Suscripción #{$subscription->id}: error al cobrar — {$e->getMessage()}");
                }
            }

            // Plazo de gracia agotado sin pago: se corta el acceso.
            $subscription->refresh();

            if ($subscription->status === 'activa' && ! $subscription->grantsAccess()) {
                $subscription->update(['status' => 'vencida']);
                $business?->owner?->notify(new SubscriptionExpiredNotification($subscription));
                Notification::send(User::superAdmins(), new AdminSubscriptionAlert('suscripcion_vencida', $business));
                $this->warn("Suscripción #{$subscription->id} ({$business->name}): gracia agotada, marcada vencida.");
            }
        }

        return self::SUCCESS;
    }
}
