<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al usuario cuando el equipo de soporte responde su ticket.
 */
class SupportTicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected SupportTicket $ticket, protected string $reply) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Respondimos tu ticket: {$this->ticket->subject}")
            ->greeting("Hola, {$notifiable->name}")
            ->line('Nuestro equipo respondió tu ticket de soporte:')
            ->line('"'.str($this->reply)->limit(300).'"')
            ->action('Ver ticket', url('/support'))
            ->line('Puedes responder desde tu panel si necesitas algo más.');
    }
}
