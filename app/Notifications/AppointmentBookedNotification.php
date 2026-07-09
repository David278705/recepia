<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentBookedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment->loadMissing(['contact', 'service']);
        $business = $appointment->business;
        $starts = $appointment->starts_at->setTimezone($business->timezone)->translatedFormat('l j \d\e F, g:i a');

        return (new MailMessage)
            ->subject("Nueva cita agendada — {$business->name}")
            ->greeting('¡Nueva cita agendada por tu recepcionista de IA! 📅')
            ->line("Cliente: {$appointment->contact->name} ({$appointment->contact->wa_id})")
            ->line("Servicio: {$appointment->service?->name}")
            ->line("Cuándo: {$starts}");
    }
}
