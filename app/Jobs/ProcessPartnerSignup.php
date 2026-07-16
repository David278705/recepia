<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\Admin\OrphanSignupNotification;
use App\Notifications\Admin\WhatsappConnectedNotification;
use App\Services\Meta\EmbeddedSignupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Alta por el link genérico alojado por Meta: el webhook account_update con
 * PARTNER_ADDED trae la WABA y el portafolio del cliente; aquí se busca el
 * negocio por número de teléfono y se aprovisiona. Si ningún negocio
 * coincide, se alerta al super_admin (alta huérfana).
 */
class ProcessPartnerSignup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected string $wabaId, protected string $ownerBusinessId) {}

    public function handle(EmbeddedSignupService $signup): void
    {
        try {
            $result = $signup->handlePartnerAdded($this->wabaId, $this->ownerBusinessId);
        } catch (\Throwable $e) {
            Log::error('Pilo: fallo procesando PARTNER_ADDED.', [
                'waba_id' => $this->wabaId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // reintento del job
        }

        if ($result === null) {
            Log::warning('Pilo: PARTNER_ADDED sin negocio que coincida por teléfono (alta huérfana).', ['waba_id' => $this->wabaId]);
            Notification::send(User::superAdmins(), new OrphanSignupNotification($this->wabaId, $this->ownerBusinessId));

            return;
        }

        $business = $result['account']->business;
        Notification::send(User::superAdmins(), new WhatsappConnectedNotification($business, $result['mode']));
    }
}
