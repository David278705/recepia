<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected const REASON_LABELS = [
        'no_sabe' => 'el bot no supo responder',
        'cliente_lo_pidio' => 'el cliente pidió hablar con una persona',
        'molestia' => 'se detectó molestia o urgencia',
        'keyword' => 'una palabra clave disparó la escalación',
        'limite_mensajes' => 'el bot alcanzó su límite de mensajes de 24 horas en esta conversación',
    ];

    public function __construct(protected Conversation $conversation, protected string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $conversation = $this->conversation->loadMissing('contact');
        $business = $conversation->business;
        $label = self::REASON_LABELS[$this->reason] ?? $this->reason;

        return (new MailMessage)
            ->subject("Un cliente necesita que lo atiendas tú — {$business->name}")
            ->greeting('Un cliente necesita que lo atiendas tú 👋')
            ->line("Cliente: {$conversation->contact->name} ({$conversation->contact->wa_id})")
            ->line("Motivo: {$label}")
            ->line(config('brand.name').' se apartó de esta conversación y le avisó al cliente que lo contactarás. Retómala desde tu panel cuando puedas.');
    }
}
