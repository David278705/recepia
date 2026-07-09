<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\Admin\AdminSubscriptionAlert;
use App\Notifications\CancellationScheduledNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentReceiptNotification;
use App\Notifications\RenewalReminderNotification;
use App\Notifications\SubscriptionEndedNotification;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\WelcomeOwnerNotification;
use App\Services\Wompi\SubscriptionBiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wompi.base_url' => 'https://sandbox.wompi.co/v1',
            'services.wompi.public_key' => 'pub_test_123',
            'services.wompi.private_key' => 'prv_test_123',
            'services.wompi.events_secret' => 'test_events_secret',
            'services.wompi.integrity_secret' => 'test_integrity_secret',
            'recepia.billing.grace_days' => 5,
        ]);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    protected function approvedPaymentFor(Subscription $subscription): SubscriptionPayment
    {
        return SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'business_id' => $subscription->business_id,
            'wompi_transaction_id' => 'trx-'.uniqid(),
            'amount_cents' => $subscription->price_cents,
            'currency' => 'COP',
            'status' => 'APPROVED',
        ]);
    }

    public function test_approved_payment_sends_receipt_to_owner_and_alert_to_admin(): void
    {
        Notification::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'status' => 'pendiente',
            'current_period_ends_at' => null,
        ]);

        app(SubscriptionBiller::class)->applyPaymentStatus($this->approvedPaymentFor($subscription));

        Notification::assertSentTo($business->owner, PaymentReceiptNotification::class);
        Notification::assertSentTo($this->admin, AdminSubscriptionAlert::class);
    }

    public function test_duplicate_approved_payment_does_not_resend_emails(): void
    {
        Notification::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create(['business_id' => $business->id, 'current_period_ends_at' => null, 'status' => 'pendiente']);
        $payment = $this->approvedPaymentFor($subscription);

        $biller = app(SubscriptionBiller::class);
        $biller->applyPaymentStatus($payment->fresh());
        $biller->applyPaymentStatus($payment->fresh());

        Notification::assertSentToTimes($business->owner, PaymentReceiptNotification::class, 1);
    }

    public function test_cancellation_sends_confirmation_and_admin_alert(): void
    {
        Notification::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create(['business_id' => $business->id]);

        $this->actingAs($business->owner)->postJson('/api/subscription/cancel')->assertOk();

        Notification::assertSentTo($business->owner, CancellationScheduledNotification::class);
        Notification::assertSentTo($this->admin, AdminSubscriptionAlert::class);
    }

    public function test_grace_expiry_sends_expired_emails(): void
    {
        Notification::fake();
        Http::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
            'current_period_ends_at' => now()->subDays(6),
        ]);

        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        Notification::assertSentTo($business->owner, SubscriptionExpiredNotification::class);
        Notification::assertSentTo($this->admin, AdminSubscriptionAlert::class);
    }

    public function test_final_cancellation_sends_ended_emails(): void
    {
        Notification::fake();
        Http::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'cancel_at_period_end' => true,
            'current_period_ends_at' => now()->subHour(),
        ]);

        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        Notification::assertSentTo($business->owner, SubscriptionEndedNotification::class);
        Notification::assertSentTo($this->admin, AdminSubscriptionAlert::class);
    }

    public function test_declined_auto_charge_sends_failure_emails_once(): void
    {
        Notification::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'tarjeta',
            'current_period_ends_at' => now()->subHour(),
        ]);

        Http::fake([
            'sandbox.wompi.co/v1/transactions/*' => Http::response([
                'data' => ['id' => 'trx-fail', 'status' => 'DECLINED', 'status_message' => 'Fondos insuficientes'],
            ]),
            'sandbox.wompi.co/v1/transactions' => Http::sequence()
                ->push(['data' => ['id' => 'trx-fail', 'status' => 'PENDING']], 201)
                ->push(['data' => ['id' => 'trx-fail-2', 'status' => 'PENDING']], 201),
        ]);

        // Primera corrida: avisa. Segunda corrida (reintento horario): silencio.
        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        Http::fake([
            'sandbox.wompi.co/v1/transactions/*' => Http::response([
                'data' => ['id' => 'trx-fail-2', 'status' => 'DECLINED', 'status_message' => 'Fondos insuficientes'],
            ]),
            'sandbox.wompi.co/v1/transactions' => Http::response(['data' => ['id' => 'trx-fail-2', 'status' => 'PENDING']], 201),
        ]);
        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        Notification::assertSentToTimes($business->owner, PaymentFailedNotification::class, 1);
    }

    public function test_reminder_command_sends_expiring_and_grace_reminders(): void
    {
        Notification::fake();

        $expiring = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $expiring->id,
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
            'current_period_ends_at' => now()->addDays(2),
        ]);

        $inGrace = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $inGrace->id,
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
            'current_period_ends_at' => now()->subDay(),
        ]);

        // Con tarjeta y periodo lejano: no debe recibir nada.
        $quiet = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $quiet->id,
            'current_period_ends_at' => now()->addDays(20),
        ]);

        $this->artisan('recepia:recordatorios-suscripcion')->assertSuccessful();

        Notification::assertSentTo($expiring->owner, RenewalReminderNotification::class);
        Notification::assertSentTo($inGrace->owner, RenewalReminderNotification::class);
        Notification::assertNothingSentTo($quiet->owner);
    }

    public function test_new_owner_receives_welcome_email(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)->postJson('/api/admin/businesses', [
            'name' => 'Barbería Nueva',
            'type' => 'barberia',
            'status' => 'activo',
            'tone' => 'cercano',
            'owner_mode' => 'new',
            'owner_name' => 'Dueño Nuevo',
            'owner_email' => 'nuevo@example.com',
            'owner_password' => 'secreto123',
        ]);

        $response->assertCreated();

        $owner = User::where('email', 'nuevo@example.com')->first();
        Notification::assertSentTo($owner, WelcomeOwnerNotification::class);
    }
}
