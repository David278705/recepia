<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Jobs\ProcessPartnerSignup;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook único global de la WhatsApp Business Cloud API. Valida la
 * verificación inicial de Meta y la firma de cada evento entrante, y
 * despacha el procesamiento pesado a una cola (Meta reintenta si tardamos).
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        if ($request->query('hub_mode') === 'subscribe'
            && $request->query('hub_verify_token') === config('services.whatsapp.verify_token')) {
            return response($request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('RecepIA: firma de webhook de WhatsApp inválida o ausente, evento rechazado.');

            return response('Forbidden', 403);
        }

        $payload = $request->all();

        $this->dispatchPartnerSignups($payload);

        ProcessIncomingMessage::dispatch($payload);

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * account_update con PARTNER_ADDED = alguien completó el Embedded Signup
     * por el link genérico alojado por Meta: se aprovisiona en cola buscando
     * el negocio por número de teléfono.
     */
    protected function dispatchPartnerSignups(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                if (($change['field'] ?? '') !== 'account_update' || ($value['event'] ?? '') !== 'PARTNER_ADDED') {
                    continue;
                }

                $wabaId = $value['waba_info']['waba_id'] ?? null;
                $ownerBusinessId = $value['waba_info']['owner_business_id'] ?? null;

                if ($wabaId && $ownerBusinessId) {
                    ProcessPartnerSignup::dispatch((string) $wabaId, (string) $ownerBusinessId);
                }
            }
        }
    }

    protected function hasValidSignature(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');

        if (! $appSecret) {
            return false;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);
        $provided = substr($header, strlen('sha256='));

        return hash_equals($expected, $provided);
    }
}
