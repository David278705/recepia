<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al dueño cuando cambia el precio de su plan. Si pagaba con cobro
 * automático a tarjeta, se le informa que la renovación quedó detenida y que
 * debe suscribirse de nuevo aceptando el precio actualizado (exigencia de
 * información previa del Estatuto del Consumidor).
 */
class SubscriptionPriceChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Subscription $subscription,
        protected int $oldPriceCents,
        protected int $newPriceCents,
        protected bool $autoRenewalStopped,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $old = '$'.number_format($this->oldPriceCents / 100, 0, ',', '.').' COP';
        $new = '$'.number_format($this->newPriceCents / 100, 0, ',', '.').' COP';
        $until = $this->subscription->current_period_ends_at?->translatedFormat('j \d\e F \d\e Y');

        $mail = (new MailMessage)
            ->subject('Cambio en el precio de tu plan — '.config('app.name'))
            ->greeting("Hola, {$notifiable->name}")
            ->line("El precio mensual de tu plan cambió de {$old} a {$new}.");

        if ($this->autoRenewalStopped) {
            $mail->line("Detuvimos el cobro automático a tu tarjeta para que nada se cobre sin tu autorización. Tu servicio sigue activo hasta el {$until}.")
                ->line('Para continuar después de esa fecha, vuelve a activar tu suscripción desde el panel aceptando el nuevo precio.')
                ->action('Ir a mi suscripción', url('/subscription'));
        } else {
            $mail->line($until ? "Tu periodo actual no cambia: el nuevo precio aplica desde tu próximo pago (tu servicio está pagado hasta el {$until})." : 'El nuevo precio aplica desde tu próximo pago.')
                ->action('Ver mi suscripción', url('/subscription'));
        }

        return $mail->line('Si tienes preguntas, responde este correo o escríbenos por el módulo de soporte.');
    }
}
