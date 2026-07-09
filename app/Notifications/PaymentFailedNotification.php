<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al dueño cuando el cobro automático a su tarjeta fue rechazado.
 */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Subscription $subscription, protected ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->subscription->accessUntil()?->translatedFormat('j \d\e F \d\e Y');

        $mail = (new MailMessage)
            ->subject('No pudimos cobrar tu suscripción ⚠️')
            ->greeting('Tu banco rechazó el cobro de este mes 😕');

        if ($this->reason) {
            $mail->line("Motivo del banco: {$this->reason}.");
        }

        return $mail
            ->line('Seguiremos reintentando el cobro automáticamente, pero también puedes pagar ya mismo con Nequi, DaviPlata o PSE desde tu panel.')
            ->line("Tienes hasta el {$deadline} para ponerte al día; después de esa fecha tu recepcionista se pausará.")
            ->action('Pagar ahora', url('/subscription'));
    }
}
