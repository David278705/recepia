<?php

use App\Models\Business;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;

// Named "login": único login de la app (Vue Router lo renderiza). Laravel usa
// route('login') internamente para redirigir invitados no autenticados en
// peticiones que no envían Accept: application/json.
Route::view('login', 'app')->name('login');

// Link firmado (48 h) que el super_admin le genera al dueño para conectar su
// WhatsApp. La firma se valida aquí (server-side) y se convierte en un token
// encriptado de corta vida que autoriza el POST de cierre del onboarding.
Route::get('connect/{business}', function (Business $business) {
    $token = Crypt::encrypt([
        'business_id' => $business->id,
        'expires_at' => now()->addHours(4)->timestamp,
    ]);

    return redirect('/connect-whatsapp?token='.urlencode($token));
})->middleware('signed')->name('whatsapp.connect');

// Retorno del dialog OAuth de Meta (variante por redirect del Embedded
// Signup): llega con ?code y nuestro ?state; debe ser GET navegable.
Route::get('whatsapp/onboarding/callback', [\App\Http\Controllers\Api\WhatsappOnboardingController::class, 'callback'])
    ->name('whatsapp.onboarding.callback');

Route::view('/{any}', 'app')->where('any', '.*');
