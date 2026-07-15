<?php

namespace App\Notifications;

use App\Models\CustomerRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al dueño cuando el bot captura un pedido o una solicitud de
 * cotización de un cliente.
 */
class CustomerRequestReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected CustomerRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->request->loadMissing('contact');
        $isOrder = $request->type === 'pedido';
        $payload = $request->payload ?? [];

        $mail = (new MailMessage)
            ->subject($isOrder ? 'Nuevo pedido por WhatsApp 🛍️' : 'Nueva solicitud de cotización 📋')
            ->greeting($isOrder ? 'Tu recepcionista tomó un pedido' : 'Tu recepcionista registró una solicitud')
            ->line("Cliente: {$request->contact->name} ({$request->contact->wa_id})");

        if ($isOrder) {
            foreach ($payload['items'] ?? [] as $item) {
                $qty = $item['cantidad'] ?? 1;
                $note = ! empty($item['nota']) ? " — {$item['nota']}" : '';
                $mail->line("• {$qty} × {$item['nombre']}{$note}");
            }

            if (! empty($payload['entrega'])) {
                $mail->line("Entrega: {$payload['entrega']}".(! empty($payload['direccion']) ? " — {$payload['direccion']}" : ''));
            }
        } else {
            $mail->line('Solicitud: '.($payload['resumen'] ?? ''));

            if (! empty($payload['detalles'])) {
                $mail->line("Detalles: {$payload['detalles']}");
            }
        }

        if (! empty($payload['nota'])) {
            $mail->line("Nota: {$payload['nota']}");
        }

        return $mail->action('Ver en el panel', url('/requests'));
    }
}
