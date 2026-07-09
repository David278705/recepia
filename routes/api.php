<?php

use App\Http\Controllers\Api\Admin\AvailableOwnersController;
use App\Http\Controllers\Api\Admin\BusinessController as AdminBusinessController;
use App\Http\Controllers\Api\Admin\ImpersonationController;
use App\Http\Controllers\Api\Admin\MetricsController;
use App\Http\Controllers\Api\Admin\SystemHealthController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BotTestController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\BusinessHourController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Controllers\Webhooks\WompiWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
    Route::post('stop-impersonating', [ImpersonationController::class, 'stop']);

    // Gestión de la suscripción: accesible sin suscripción activa (es el
    // onboarding de pago).
    Route::get('subscription', [SubscriptionController::class, 'show']);
    Route::post('subscription/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::get('subscription/banks', [SubscriptionController::class, 'banks']);
    Route::post('subscription/pay', [SubscriptionController::class, 'pay']);
    Route::post('subscription/confirm', [SubscriptionController::class, 'confirm']);
    Route::delete('subscription/card', [SubscriptionController::class, 'deleteCard']);
    Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('subscription/resume', [SubscriptionController::class, 'resume']);

    // Panel del owner: requiere suscripción activa (paywall).
    Route::middleware('subscription')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'show']);

        Route::get('business', [BusinessController::class, 'show']);
        Route::put('business', [BusinessController::class, 'update']);

        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
        Route::post('conversations/{conversation}/take-over', [ConversationController::class, 'takeOver']);
        Route::post('conversations/{conversation}/return-to-bot', [ConversationController::class, 'returnToBot']);

        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);

        Route::get('contacts', [ContactController::class, 'index']);

        Route::apiResource('services', ServiceController::class)->except(['show']);
        Route::apiResource('business-hours', BusinessHourController::class)->except(['show']);
        Route::apiResource('faqs', FaqController::class)->except(['show']);

        Route::post('bot-test', BotTestController::class);
    });

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::apiResource('businesses', AdminBusinessController::class);
        Route::get('available-owners', [AvailableOwnersController::class, 'index']);
        Route::post('businesses/{business}/impersonate', [ImpersonationController::class, 'start']);

        Route::get('metrics', [MetricsController::class, 'show']);
        Route::get('system-health', [SystemHealthController::class, 'show']);
    });
});

Route::middleware('throttle:whatsapp-webhook')->group(function () {
    Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);
});

Route::post('webhooks/wompi', [WompiWebhookController::class, 'handle'])->middleware('throttle:60,1');
