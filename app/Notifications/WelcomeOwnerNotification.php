<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bienvenida al dueño cuando el admin crea su cuenta y su negocio.
 */
class WelcomeOwnerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Business $business) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("Tu recepcionista de IA está listo — {$this->business->name}")
            ->greeting("¡Bienvenido a {$appName}, {$notifiable->name}! 👋")
            ->line("Creamos tu cuenta para administrar el recepcionista de WhatsApp de {$this->business->name}.")
            ->line("Tu usuario es este correo ({$notifiable->email}). La contraseña te la comparte nuestro equipo por un canal seguro.")
            ->action('Entrar a mi panel', url('/login'))
            ->line('Desde el panel puedes ver tus conversaciones, tus citas y configurar todo lo que tu recepcionista sabe de tu negocio.');
    }
}
