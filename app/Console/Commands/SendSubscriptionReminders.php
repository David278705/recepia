<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\RenewalReminderNotification;
use Illuminate\Console\Command;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'recepia:recordatorios-suscripcion';

    protected $description = 'Envía los recordatorios de renovación: antes de que venza el mes (pago manual) y durante los días de gracia.';

    public function handle(): int
    {
        // "Tu mes vence pronto": solo para quien paga manualmente (Nequi/
        // DaviPlata/PSE); a las tarjetas se les cobra solo. Se envía cuando
        // faltan 3 días o menos.
        $expiringSoon = Subscription::query()
            ->where('status', 'activa')
            ->where('cancel_at_period_end', false)
            ->whereNull('wompi_payment_source_id')
            ->whereBetween('current_period_ends_at', [now(), now()->addDays(3)])
            ->with('business.owner')
            ->get();

        foreach ($expiringSoon as $subscription) {
            $subscription->business?->owner?->notify(new RenewalReminderNotification($subscription, 'por_vencer'));
            $this->info("Recordatorio 'por vencer' → {$subscription->business->name}.");
        }

        // "Estás en el plazo de gracia": para todos los que ya vencieron y
        // aún conservan acceso — un empujón diario hasta que paguen.
        $inGrace = Subscription::query()
            ->where('status', 'activa')
            ->where('cancel_at_period_end', false)
            ->where('current_period_ends_at', '<=', now())
            ->with('business.owner')
            ->get()
            ->filter(fn (Subscription $s) => $s->grantsAccess());

        foreach ($inGrace as $subscription) {
            $subscription->business?->owner?->notify(new RenewalReminderNotification($subscription, 'en_gracia'));
            $this->info("Recordatorio 'en gracia' → {$subscription->business->name}.");
        }

        return self::SUCCESS;
    }
}
