<?php

namespace App\Notifications\Admin;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alertas de facturación para el super admin: un solo correo parametrizado
 * por tipo de evento, siempre con el negocio y el contexto del caso.
 *
 * Tipos: pago_recibido, cobro_fallido, suscripcion_vencida,
 * cancelacion_programada, cancelacion_reanudada, suscripcion_finalizada.
 *
 * @param array{amount_cents?: int, activation?: bool, reason?: string, period_ends_at?: string, method?: string} $context
 */
class AdminSubscriptionAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $type,
        protected Business $business,
        protected array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->business->name;
        $owner = $this->business->owner;
        $amount = isset($this->context['amount_cents'])
            ? '$'.number_format($this->context['amount_cents'] / 100, 0, ',', '.').' COP'
            : null;

        $mail = match ($this->type) {
            'pago_recibido' => (new MailMessage)
                ->subject(($this->context['activation'] ?? false)
                    ? "💰 Nueva suscripción activada — {$name}"
                    : "💰 Renovación pagada — {$name}")
                ->greeting(($this->context['activation'] ?? false) ? '¡Nueva suscripción activa!' : 'Renovación pagada')
                ->line("{$name} pagó {$amount}".(isset($this->context['method']) ? " ({$this->context['method']})" : '').'.')
                ->line(isset($this->context['period_ends_at']) ? "Periodo activo hasta: {$this->context['period_ends_at']}." : ''),

            'cobro_fallido' => (new MailMessage)
                ->subject("⚠️ Cobro rechazado — {$name}")
                ->greeting('Un cobro automático falló')
                ->line("El cobro de {$amount} a la tarjeta de {$name} fue rechazado".(isset($this->context['reason']) ? " ({$this->context['reason']})" : '').'.')
                ->line('Se reintentará automáticamente durante los días de gracia; el dueño ya recibió su aviso.'),

            'suscripcion_vencida' => (new MailMessage)
                ->subject("🚫 Suscripción vencida — {$name}")
                ->greeting('Un negocio perdió el acceso')
                ->line("Se agotó el plazo de gracia de {$name} sin pago: la suscripción quedó vencida y su panel bloqueado.")
                ->line('Considera contactar al dueño para recuperarlo.'),

            'cancelacion_programada' => (new MailMessage)
                ->subject("📋 Cancelación programada — {$name}")
                ->greeting('Un cliente programó su cancelación')
                ->line("{$name} canceló la renovación.".(isset($this->context['period_ends_at']) ? " Conserva el acceso hasta {$this->context['period_ends_at']}." : ''))
                ->line('Buen momento para preguntarle qué pasó.'),

            'cancelacion_reanudada' => (new MailMessage)
                ->subject("🔄 Cancelación revertida — {$name}")
                ->greeting('¡Un cliente se queda!')
                ->line("{$name} reanudó la renovación de su suscripción."),

            'suscripcion_finalizada' => (new MailMessage)
                ->subject("👋 Suscripción finalizada — {$name}")
                ->greeting('Una cancelación se hizo efectiva')
                ->line("La suscripción de {$name} terminó junto con su periodo pagado."),

            default => (new MailMessage)
                ->subject("Evento de suscripción — {$name}")
                ->line("Evento: {$this->type}."),
        };

        if ($owner) {
            $mail->line("Dueño: {$owner->name} ({$owner->email}).");
        }

        return $mail->action('Ver métricas', url('/admin/metrics'));
    }
}
