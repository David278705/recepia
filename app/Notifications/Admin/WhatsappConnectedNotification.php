<?php

namespace App\Notifications\Admin;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resumen al super_admin cuando un negocio completa el Embedded Signup.
 */
class WhatsappConnectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Business $business, protected string $mode) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $account = $this->business->whatsappAccount;

        return (new MailMessage)
            ->subject("WhatsApp conectado — {$this->business->name} ✅")
            ->greeting('Nuevo número conectado por Embedded Signup')
            ->line("Negocio: {$this->business->name}")
            ->line('Número: '.($account?->phone_e164 ?? '—').' ('.($account?->verified_name ?? 'sin nombre verificado').')')
            ->line("Modo: {$this->mode}")
            ->action('Ver en el panel', url('/admin/system-health'));
    }
}
