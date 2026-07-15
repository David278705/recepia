<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alguien completó el signup por el link genérico de Meta pero ningún negocio
 * de la plataforma coincide por número de teléfono.
 */
class OrphanSignupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $wabaId, protected string $ownerBusinessId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠️ Signup de WhatsApp sin negocio asignado')
            ->greeting('Un signup quedó huérfano')
            ->line("Alguien completó el Embedded Signup por el link genérico, pero ningún negocio tiene registrado el teléfono de esa cuenta (WABA {$this->wabaId}, portafolio {$this->ownerBusinessId}).")
            ->line('Qué hacer: crea (o corrige) el negocio en el panel con el número de celular EXACTO del cliente y pídele repetir el flujo, o completa el alta manual con estos IDs.')
            ->action('Ir al panel', url('/admin/businesses'));
    }
}
