<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Genera el link firmado (48 h) para que el dueño conecte su WhatsApp desde
 * su propio computador, con su propia sesión de Facebook, sin necesitar
 * usuario en la plataforma.
 */
class ConnectLinkController extends Controller
{
    public function store(Business $business): JsonResponse
    {
        $url = URL::temporarySignedRoute('whatsapp.connect', now()->addHours(48), ['business' => $business->id]);

        return response()->json(['data' => ['url' => $url, 'expires_in_hours' => 48]]);
    }

    /**
     * Variante por redirect (sin popup ni SDK): URL del dialog OAuth de
     * Facebook Login for Business. Meta vuelve a nuestro callback con ?code y
     * el ?state que lleva el business_id encriptado (48 h).
     */
    public function oauth(Business $business): JsonResponse
    {
        $state = Crypt::encrypt([
            'business_id' => $business->id,
            'expires_at' => now()->addHours(48)->timestamp,
        ]);

        $url = 'https://www.facebook.com/'.config('services.meta.graph_version').'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.meta.app_id'),
            'config_id' => config('services.meta.es_config_id'),
            'redirect_uri' => route('whatsapp.onboarding.callback'),
            'response_type' => 'code',
            'state' => $state,
        ]);

        return response()->json(['data' => ['url' => $url, 'expires_in_hours' => 48]]);
    }
}
