<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al dueño: se agotó el plazo para pagar y su recepcionista se pausó.
 */
class SubscriptionExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu recepcionista está en pausa 🚫')
            ->greeting('Tu suscripción venció')
            ->line('Se agotó el plazo para pagar la renovación, así que tu recepcionista de IA dejó de responder y tu panel quedó bloqueado.')
            ->line('La buena noticia: pagando ahora todo vuelve a funcionar de inmediato, con tu configuración intacta.')
            ->action('Reactivar mi suscripción', url('/subscription'));
    }
}
