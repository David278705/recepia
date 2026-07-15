<?php

namespace App\Notifications\Admin;

use App\Models\WhatsappAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta al super_admin cuando la conexión de WhatsApp de un negocio se
 * degradó (el caso típico en coexistencia: el dueño no abrió su app
 * WhatsApp Business en ~13-14 días y Meta desconectó el número).
 */
class WhatsappConnectionAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected WhatsappAccount $account) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->account->business;

        $mail = (new MailMessage)
            ->subject("⚠️ Conexión de WhatsApp degradada — {$business->name}")
            ->greeting('Una conexión de WhatsApp dejó de responder')
            ->line("Negocio: {$business->name} ({$this->account->phone_e164})")
            ->line('La Graph API ya no responde por este número: el bot no está recibiendo ni enviando mensajes.');

        if ($this->account->mode === 'coexistence') {
            $mail->line('Causa más probable (coexistencia): el dueño no ha abierto su app de WhatsApp Business en ~13-14 días y Meta desconectó el número.')
                ->line('Remedio: pídele al dueño que abra la app de WhatsApp Business en su celular. Si no se recupera, habrá que reconectar con el Embedded Signup.');
        } else {
            $mail->line('Revisa el token y el estado del número en el panel de Meta.');
        }

        return $mail->action('Ver salud del sistema', url('/admin/system-health'));
    }
}
