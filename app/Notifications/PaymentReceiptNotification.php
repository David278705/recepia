<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Recibo para el dueño por cada pago de suscripción aprobado.
 */
class PaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected SubscriptionPayment $payment, protected bool $wasActivation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->payment->subscription;
        $amount = '$'.number_format($this->payment->amount_cents / 100, 0, ',', '.').' COP';
        $until = $subscription->current_period_ends_at?->translatedFormat('j \d\e F \d\e Y');

        $mail = (new MailMessage)
            ->subject($this->wasActivation ? '¡Tu suscripción quedó activa! 🎉' : 'Pago recibido — tu suscripción sigue activa')
            ->greeting($this->wasActivation ? '¡Gracias por suscribirte! 🎉' : '¡Pago recibido! ✅')
            ->line("Recibimos tu pago de {$amount}.")
            ->line("Tu suscripción está activa hasta el {$until}.");

        if ($subscription->payment_method !== 'tarjeta') {
            $mail->line('Cuando se acerque la fecha te recordaremos hacer el siguiente pago.');
        }

        return $mail->action('Ver mi suscripción', url('/subscription'));
    }
}
