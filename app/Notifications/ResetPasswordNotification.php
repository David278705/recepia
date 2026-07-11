<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de restablecimiento de contraseña, en español y con el enlace
 * apuntando a la SPA (/reset-password) en lugar de la ruta web por defecto.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/reset-password?token='.$this->token.'&email='.urlencode($notifiable->getEmailForPasswordReset()));
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablece tu contraseña — '.config('app.name'))
            ->greeting("Hola, {$notifiable->name}")
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace expira en {$minutes} minutos.")
            ->line('Si no solicitaste este cambio, puedes ignorar este correo: tu contraseña seguirá siendo la misma.');
    }
}
