<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmación al dueño de que su cancelación quedó programada.
 */
class CancellationScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $until = $this->subscription->current_period_ends_at?->translatedFormat('j \d\e F \d\e Y');

        return (new MailMessage)
            ->subject('Tu cancelación quedó programada')
            ->greeting('Confirmamos tu cancelación 👍')
            ->line("Tu recepcionista seguirá funcionando hasta el {$until} (el periodo que ya pagaste). No haremos más cobros.")
            ->line('Si cambias de opinión antes de esa fecha, puedes reanudar la renovación desde tu panel con un clic.')
            ->action('Ver mi suscripción', url('/subscription'));
    }
}
