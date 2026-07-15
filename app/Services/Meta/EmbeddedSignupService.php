<?php

namespace App\Services\Meta;

use App\Models\Business;
use App\Models\OnboardingLog;
use App\Models\WhatsappAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Aprovisionamiento de un negocio vía Embedded Signup de Meta (con soporte de
 * coexistencia). Tres puertas de entrada, todas terminan en provision():
 *
 * - complete(): popup del SDK u OAuth por redirect — canjea el code por el
 *   business token del cliente y descubre los activos si no llegaron.
 * - handlePartnerAdded(): link genérico alojado por Meta — el webhook
 *   PARTNER_ADDED trae waba_id + owner_business_id; el token se obtiene con
 *   nuestro system user token y el negocio se encuentra POR NÚMERO de
 *   teléfono (el admin crea el negocio solo con el celular).
 *
 * Cada paso queda en onboarding_logs (sin tokens) para depurar altas
 * fallidas. Los flujos son idempotentes: pueden reintentarse completos.
 */
class EmbeddedSignupService
{
    /**
     * @return array{account: WhatsappAccount, mode: string}
     */
    public function complete(Business $business, string $code, ?string $phoneNumberId, ?string $wabaId, bool $overwrite = false, ?string $redirectUri = null): array
    {
        $this->assertOverwriteAllowed($business, $phoneNumberId, $overwrite);

        // 1. Canje del code por el business token del cliente. Un code de
        //    redirect exige el mismo redirect_uri del dialog; el del popup no.
        $token = $this->step($business, 'canje_token', function () use ($code, $redirectUri) {
            $response = Http::get($this->graphUrl('oauth/access_token'), array_filter([
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]));

            $accessToken = $response->json('access_token');

            if (! $response->successful() || ! $accessToken) {
                throw $this->metaError($response, 'No pudimos canjear la autorización de Meta. Vuelve a intentar el flujo desde el principio.');
            }

            return $accessToken;
        });

        // 1b. Flujo por redirect: el dialog OAuth no entrega los IDs de los
        //     activos — se descubren a partir de lo que el token autoriza.
        if (! $wabaId || ! $phoneNumberId) {
            [$wabaId, $phoneNumberId] = $this->step($business, 'descubrir_activos', function () use ($token, $wabaId, $phoneNumberId) {
                return $this->discoverAssets($token, $wabaId, $phoneNumberId);
            });
        }

        return $this->provision($business, $token, $phoneNumberId, $wabaId, $overwrite);
    }

    /**
     * Alta vía link genérico alojado por Meta: llega el webhook account_update
     * con PARTNER_ADDED (waba_id + owner_business_id, SIN número). Se obtiene
     * el business token con nuestro system user token, se listan los números
     * de la WABA y se busca el negocio cuyo teléfono coincida.
     *
     * @return array{account: WhatsappAccount, mode: string}|null null = sin negocio que coincida (alta huérfana)
     */
    public function handlePartnerAdded(string $wabaId, string $ownerBusinessId): ?array
    {
        $token = $this->fetchBusinessToken($ownerBusinessId);

        $response = Http::withToken($token)->get($this->graphUrl("{$wabaId}/phone_numbers"), [
            'fields' => 'id,display_phone_number',
        ]);

        if (! $response->successful()) {
            throw $this->metaError($response, 'PARTNER_ADDED: no pudimos listar los números de la WABA.');
        }

        foreach ($response->json('data') ?? [] as $phone) {
            $business = $this->matchBusinessByPhone((string) ($phone['display_phone_number'] ?? ''));

            if ($business) {
                return $this->provision($business, $token, (string) $phone['id'], $wabaId, overwrite: true);
            }
        }

        return null;
    }

    /**
     * Business token del cliente a partir de nuestro system user token
     * (System User Access Tokens API — flujo del Hosted Embedded Signup).
     */
    public function fetchBusinessToken(string $ownerBusinessId): string
    {
        $systemToken = (string) config('services.meta.system_user_token');

        if ($systemToken === '') {
            throw new OnboardingException('META_SYSTEM_USER_TOKEN no está configurado: no se puede procesar el alta por link genérico.');
        }

        $response = Http::withToken($systemToken)->post($this->graphUrl("{$ownerBusinessId}/system_user_access_tokens"), [
            'appsecret_proof' => hash_hmac('sha256', $systemToken, (string) config('services.meta.app_secret')),
            'fetch_only' => 'true',
        ]);

        $token = $response->json('business_token') ?? $response->json('access_token');

        if (! $response->successful() || ! $token) {
            throw $this->metaError($response, 'PARTNER_ADDED: no pudimos obtener el token para operar la WABA del cliente.');
        }

        return $token;
    }

    /**
     * Negocio cuyo teléfono coincide con el número conectado: se comparan los
     * dígitos completos o los últimos 10 (celular colombiano sin indicativo).
     */
    public function matchBusinessByPhone(string $displayPhone): ?Business
    {
        $incoming = preg_replace('/\D+/', '', $displayPhone) ?? '';

        if (strlen($incoming) < 7) {
            return null;
        }

        return Business::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->first(function (Business $business) use ($incoming) {
                $own = preg_replace('/\D+/', '', (string) $business->phone) ?? '';

                return $own !== '' && (
                    $own === $incoming
                    || substr($own, -10) === substr($incoming, -10)
                );
            })
            ?->fresh();
    }

    /**
     * Tramo común de aprovisionamiento: webhooks, verificación del número,
     * registro (solo ruta dedicada) y persistencia.
     *
     * @return array{account: WhatsappAccount, mode: string}
     */
    protected function provision(Business $business, string $token, string $phoneNumberId, string $wabaId, bool $overwrite = false): array
    {
        $this->assertOverwriteAllowed($business, $phoneNumberId, $overwrite);

        $existing = $business->whatsappAccount;

        // 2. Suscripción de webhooks — sin esto el número queda "sordo".
        $this->step($business, 'suscripcion_webhooks', function () use ($wabaId, $token) {
            $response = Http::withToken($token)->post($this->graphUrl("{$wabaId}/subscribed_apps"));

            if (! $response->successful() || ! $response->json('success')) {
                throw $this->metaError($response, 'La cuenta quedó autorizada pero no pudimos suscribir los webhooks. Reintenta; si persiste, contáctanos.');
            }

            return true;
        });

        // 3. Consulta del número: nombre visible, E.164 y tipo de plataforma
        //    (de aquí sale si es coexistencia o ruta dedicada).
        $info = $this->step($business, 'consulta_numero', function () use ($phoneNumberId, $token) {
            $response = Http::withToken($token)->get($this->graphUrl($phoneNumberId), [
                'fields' => 'verified_name,display_phone_number,quality_rating,platform_type',
            ]);

            if (! $response->successful()) {
                throw $this->metaError($response, 'No pudimos verificar el número recién conectado.');
            }

            return $response->json();
        });

        // Coexistencia = el número sigue viviendo en la app de WhatsApp
        // Business (platform_type SMB_APP / SMB).
        $mode = str_starts_with(strtoupper((string) ($info['platform_type'] ?? '')), 'SMB') ? 'coexistence' : 'dedicado';

        // 4. Registro del número. En coexistencia se OMITE: el número ya está
        //    registrado en la app del dueño (documentación oficial de
        //    "Onboarding business app users"). Solo la ruta dedicada registra
        //    con PIN de verificación en dos pasos.
        $pin = $existing?->two_step_pin ?? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($mode === 'dedicado') {
            $this->step($business, 'registro_numero', function () use ($phoneNumberId, $token, $pin) {
                $response = Http::withToken($token)->post($this->graphUrl("{$phoneNumberId}/register"), [
                    'messaging_product' => 'whatsapp',
                    'pin' => $pin,
                ]);

                if (! $response->successful() || ! $response->json('success')) {
                    throw $this->metaError($response, 'No pudimos registrar el número en la Cloud API.');
                }

                return true;
            });
        } else {
            $this->log($business, 'registro_numero', 'skipped', message: 'Número en coexistencia: ya registrado en la app de WhatsApp Business.');
        }

        // 5. Persistir el estado final de la cuenta.
        $account = $business->whatsappAccount()->updateOrCreate(['business_id' => $business->id], [
            'phone_number_id' => $phoneNumberId,
            'waba_id' => $wabaId,
            'phone_e164' => $this->normalizeE164($info['display_phone_number'] ?? ''),
            'verified_name' => $info['verified_name'] ?? null,
            'quality_rating' => $info['quality_rating'] ?? null,
            'access_token' => $token,
            'two_step_pin' => $pin,
            'verify_token' => config('services.whatsapp.verify_token'),
            'mode' => $mode,
            'connection_status' => 'conectado',
            'connected_at' => now(),
            'last_checked_at' => now(),
        ]);

        $this->log($business, 'completado', 'ok', message: "Conectado en modo {$mode}.");

        return ['account' => $account->fresh(), 'mode' => $mode];
    }

    protected function assertOverwriteAllowed(Business $business, ?string $phoneNumberId, bool $overwrite): void
    {
        $existing = $business->whatsappAccount;

        if ($existing && $existing->access_token && $phoneNumberId && $existing->phone_number_id !== $phoneNumberId && ! $overwrite) {
            throw new OnboardingException(
                'Este negocio ya tiene un número conectado. Confirma la sobreescritura para reemplazarlo.',
                requiresConfirmation: true,
            );
        }
    }

    /**
     * Estado actual del número contra la Graph API — lo usa el comando de
     * monitoreo diario. Devuelve null si la consulta falla.
     */
    public function checkNumber(WhatsappAccount $account): ?array
    {
        $response = Http::withToken($account->access_token)->get($this->graphUrl($account->phone_number_id), [
            'fields' => 'verified_name,display_phone_number,quality_rating,platform_type',
        ]);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Descubre la WABA y el número que el business token autoriza:
     * debug_token → granular_scopes de whatsapp_business_management →
     * target_ids (WABAs) → /{waba}/phone_numbers.
     *
     * @return array{0: string, 1: string} [waba_id, phone_number_id]
     */
    protected function discoverAssets(string $token, ?string $wabaId, ?string $phoneNumberId): array
    {
        if (! $wabaId) {
            $appToken = config('services.meta.app_id').'|'.config('services.meta.app_secret');

            $response = Http::withToken($appToken)->get($this->graphUrl('debug_token'), ['input_token' => $token]);

            if (! $response->successful()) {
                throw $this->metaError($response, 'No pudimos identificar la cuenta de WhatsApp autorizada.');
            }

            $wabaId = collect($response->json('data.granular_scopes') ?? [])
                ->firstWhere('scope', 'whatsapp_business_management')['target_ids'][0] ?? null;

            if (! $wabaId) {
                throw new OnboardingException('La autorización no incluye ninguna cuenta de WhatsApp Business. Repite el flujo y selecciona tu cuenta.');
            }
        }

        if (! $phoneNumberId) {
            $response = Http::withToken($token)->get($this->graphUrl("{$wabaId}/phone_numbers"), ['fields' => 'id,display_phone_number']);

            if (! $response->successful()) {
                throw $this->metaError($response, 'No pudimos listar los números de la cuenta de WhatsApp autorizada.');
            }

            $phoneNumberId = $response->json('data.0.id');

            if (! $phoneNumberId) {
                throw new OnboardingException('La cuenta de WhatsApp autorizada no tiene números conectados.');
            }
        }

        return [(string) $wabaId, (string) $phoneNumberId];
    }

    protected function step(Business $business, string $step, callable $callback): mixed
    {
        try {
            $result = $callback();
            $this->log($business, $step, 'ok');

            return $result;
        } catch (OnboardingException $e) {
            $this->log($business, $step, 'error', $e->metaErrorCode, $e->getMessage());

            throw $e;
        } catch (\Throwable $e) {
            $this->log($business, $step, 'error', null, $e->getMessage());

            throw new OnboardingException("Error inesperado en el paso {$step}. Reintenta en unos minutos.");
        }
    }

    protected function log(Business $business, string $step, string $status, ?string $metaErrorCode = null, ?string $message = null): void
    {
        OnboardingLog::create([
            'business_id' => $business->id,
            'step' => $step,
            'status' => $status,
            'meta_error_code' => $metaErrorCode,
            'message' => $message,
        ]);
    }

    protected function metaError(Response $response, string $friendly): OnboardingException
    {
        $error = $response->json('error') ?? [];

        return new OnboardingException(
            $friendly,
            metaErrorCode: isset($error['code']) ? (string) $error['code'] : null,
            metaMessage: $error['message'] ?? null,
        );
    }

    protected function graphUrl(string $path): string
    {
        $version = config('services.meta.graph_version', 'v23.0');

        return "https://graph.facebook.com/{$version}/{$path}";
    }

    protected function normalizeE164(string $displayPhone): string
    {
        $digits = preg_replace('/[^\d+]/', '', $displayPhone) ?? '';

        return $digits !== '' && ! str_starts_with($digits, '+') ? "+{$digits}" : $digits;
    }
}
