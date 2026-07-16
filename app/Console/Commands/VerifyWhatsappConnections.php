<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WhatsappAccount;
use App\Notifications\Admin\WhatsappConnectionAlert;
use App\Services\Meta\EmbeddedSignupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class VerifyWhatsappConnections extends Command
{
    protected $signature = 'pilo:verificar-conexiones';

    protected $description = 'Verifica contra la Graph API el estado de cada número conectado y alerta si una conexión se degradó.';

    public function handle(EmbeddedSignupService $signup): int
    {
        $accounts = WhatsappAccount::query()
            ->whereNotNull('access_token')
            ->where('connection_status', '!=', 'pendiente')
            ->with('business')
            ->get();

        foreach ($accounts as $account) {
            $info = $signup->checkNumber($account);

            if ($info === null) {
                $wasHealthy = $account->connection_status === 'conectado';

                $account->update(['connection_status' => 'error', 'last_checked_at' => now()]);

                // Alertar solo en la transición sano → degradado, no en cada
                // corrida diaria mientras siga caído.
                if ($wasHealthy) {
                    Notification::send(User::superAdmins(), new WhatsappConnectionAlert($account->fresh()));
                }

                $this->warn("{$account->business->name}: conexión degradada.");

                continue;
            }

            $account->update([
                'connection_status' => 'conectado',
                'quality_rating' => $info['quality_rating'] ?? $account->quality_rating,
                'verified_name' => $info['verified_name'] ?? $account->verified_name,
                'last_checked_at' => now(),
            ]);

            $this->info("{$account->business->name}: OK.");
        }

        return self::SUCCESS;
    }
}
