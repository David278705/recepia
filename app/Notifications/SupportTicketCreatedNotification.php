<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los super admins cuando un usuario abre un ticket de soporte.
 */
class SupportTicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nuevo ticket de soporte ({$this->ticket->type}): {$this->ticket->subject}")
            ->greeting("Hola, {$notifiable->name}")
            ->line("{$this->ticket->user->name} ({$this->ticket->user->email}) abrió un ticket de tipo \"{$this->ticket->type}\".")
            ->line("Asunto: {$this->ticket->subject}")
            ->line('"'.str($this->ticket->message)->limit(300).'"')
            ->action('Ver ticket', url('/admin/support'));
    }
}
