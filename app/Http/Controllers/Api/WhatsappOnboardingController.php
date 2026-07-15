<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Notifications\Admin\WhatsappConnectedNotification;
use App\Services\Meta\EmbeddedSignupService;
use App\Services\Meta\OnboardingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;

class WhatsappOnboardingController extends Controller
{
    /**
     * Configuración pública que el frontend necesita para lanzar el popup del
     * Embedded Signup (nunca incluye el app secret).
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'app_id' => config('services.meta.app_id'),
            'config_id' => config('services.meta.es_config_id'),
            'graph_version' => config('services.meta.graph_version'),
            'ready' => (bool) (config('services.meta.app_id') && config('services.meta.es_config_id')),
        ]);
    }

    /**
     * Cierra el flujo: canjea el code, suscribe webhooks y aprovisiona la
     * cuenta. Autorizado por sesión de super_admin (business_id) o por el
     * token del link firmado (onboarding_token).
     */
    public function complete(Request $request, EmbeddedSignupService $signup): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'phone_number_id' => ['required', 'string', 'max:64'],
            'waba_id' => ['required', 'string', 'max:64'],
            'business_id' => ['sometimes', 'integer'],
            'onboarding_token' => ['sometimes', 'string'],
            'overwrite' => ['sometimes', 'boolean'],
        ]);

        $business = $this->resolveBusiness($request, $data);

        if (! $business) {
            return response()->json(['message' => 'El enlace no es válido o ya expiró. Pide uno nuevo.'], 403);
        }

        try {
            $result = $signup->complete(
                $business,
                $data['code'],
                $data['phone_number_id'],
                $data['waba_id'],
                (bool) ($data['overwrite'] ?? false),
            );
        } catch (OnboardingException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'meta_error_code' => $e->metaErrorCode,
                'requires_confirmation' => $e->requiresConfirmation,
            ], $e->requiresConfirmation ? 409 : 422);
        }

        Notification::send(User::superAdmins(), new WhatsappConnectedNotification($business->fresh(), $result['mode']));

        return response()->json([
            'data' => [
                'business' => $business->name,
                'mode' => $result['mode'],
                'phone' => $result['account']->phone_e164,
                'verified_name' => $result['account']->verified_name,
            ],
        ]);
    }

    /**
     * Retorno del flujo OAuth de Facebook Login for Business (variante por
     * redirect, sin popup): Meta redirige el navegador aquí con ?code y el
     * ?state que nosotros generamos (lleva el business_id encriptado). El
     * dialog NO entrega waba_id/phone_number_id — el servicio los descubre a
     * partir del token. Redirige a la SPA con el resultado.
     */
    public function callback(Request $request, EmbeddedSignupService $signup)
    {
        $fail = fn (string $message) => redirect('/connect-whatsapp?status=error&message='.urlencode($message));

        // Cancelación o error reportado por Meta en la query.
        if ($request->query('error')) {
            return $fail($request->query('error_description') ?: 'Meta canceló o rechazó la autorización. Vuelve a intentar el flujo.');
        }

        $business = null;

        try {
            $payload = Crypt::decrypt((string) $request->query('state'));

            if (($payload['expires_at'] ?? 0) >= now()->timestamp) {
                $business = Business::find($payload['business_id'] ?? null);
            }
        } catch (\Throwable) {
            // state ausente o manipulado → $business queda null.
        }

        if (! $business || ! $request->query('code')) {
            return $fail('El enlace de conexión no es válido o ya expiró. Pide uno nuevo.');
        }

        try {
            $result = $signup->complete(
                $business,
                (string) $request->query('code'),
                $request->query('phone_number_id'),
                $request->query('waba_id'),
                overwrite: (bool) ($payload['overwrite'] ?? false),
                redirectUri: route('whatsapp.onboarding.callback'),
            );
        } catch (OnboardingException $e) {
            return $fail($e->getMessage());
        }

        Notification::send(User::superAdmins(), new WhatsappConnectedNotification($business->fresh(), $result['mode']));

        return redirect('/connect-whatsapp?status=success&mode='.$result['mode'].'&phone='.urlencode($result['account']->phone_e164));
    }

    /**
     * El negocio autorizado: por token de link firmado (dueños sin sesión) o
     * por sesión de super_admin con business_id explícito.
     */
    protected function resolveBusiness(Request $request, array $data): ?Business
    {
        if (! empty($data['onboarding_token'])) {
            try {
                $payload = Crypt::decrypt($data['onboarding_token']);
            } catch (\Throwable) {
                return null;
            }

            if (($payload['expires_at'] ?? 0) < now()->timestamp) {
                return null;
            }

            return Business::find($payload['business_id'] ?? null);
        }

        $user = $request->user();

        if ($user?->isSuperAdmin() && ! empty($data['business_id'])) {
            return Business::find($data['business_id']);
        }

        return null;
    }
}
