<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Recordatorio de renovación para el dueño: antes de que venza el mes
 * ('por_vencer') o durante los días de gracia ('en_gracia').
 */
class RenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Subscription $subscription, protected string $kind) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = '$'.number_format($this->subscription->price_cents / 100, 0, ',', '.').' COP';
        $periodEnd = $this->subscription->current_period_ends_at?->translatedFormat('j \d\e F');
        $deadline = $this->subscription->accessUntil()?->translatedFormat('j \d\e F');

        if ($this->kind === 'por_vencer') {
            return (new MailMessage)
                ->subject('Tu suscripción vence pronto 📅')
                ->greeting('¡Hola! Un recordatorio rápido 👋')
                ->line("Tu mes de suscripción vence el {$periodEnd}.")
                ->line("Para que tu recepcionista siga atendiendo sin pausas, paga {$amount} con Nequi, DaviPlata o PSE cuando quieras.")
                ->action('Pagar mi suscripción', url('/subscription'));
        }

        return (new MailMessage)
            ->subject('Tu suscripción está pendiente de pago ⏳')
            ->greeting('Tu mes venció y aún no registramos el pago 😕')
            ->line("Tienes hasta el {$deadline} para pagar {$amount}; después de esa fecha tu recepcionista dejará de responder a tus clientes.")
            ->action('Pagar ahora', url('/subscription'));
    }
}
