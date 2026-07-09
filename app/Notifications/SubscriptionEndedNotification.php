<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al dueño: su cancelación programada se hizo efectiva.
 */
class SubscriptionEndedNotification extends Notification implements ShouldQueue
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
            ->subject('Tu suscripción terminó')
            ->greeting('Tu suscripción llegó a su fin 👋')
            ->line('Como lo pediste, tu suscripción terminó junto con el periodo pagado: tu recepcionista dejó de responder y el panel quedó bloqueado.')
            ->line('Guardamos toda tu configuración (servicios, horarios, preguntas frecuentes): si vuelves, todo estará como lo dejaste.')
            ->action('Volver a suscribirme', url('/subscription'));
    }
}
