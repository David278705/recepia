<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wompi.base_url' => 'https://sandbox.wompi.co/v1',
            'services.wompi.public_key' => 'pub_test_123',
            'services.wompi.private_key' => 'prv_test_123',
            'services.wompi.events_secret' => 'test_events_secret',
            'services.wompi.integrity_secret' => 'test_integrity_secret',
        ]);
    }

    protected function fakeWompiHappyPath(): void
    {
        Http::fake([
            'sandbox.wompi.co/v1/merchants/*' => Http::response([
                'data' => ['presigned_acceptance' => ['acceptance_token' => 'acc_tok', 'permalink' => 'https://wompi.co/terms']],
            ]),
            'sandbox.wompi.co/v1/tokens/cards' => Http::response([
                'data' => ['id' => 'tok_test_visa', 'brand' => 'VISA', 'last_four' => '4242'],
            ], 201),
            'sandbox.wompi.co/v1/payment_sources' => Http::response([
                'data' => ['id' => 9911, 'public_data' => ['brand' => 'VISA', 'last_four' => '4242'], 'status' => 'AVAILABLE'],
            ], 201),
            'sandbox.wompi.co/v1/transactions/*' => Http::response([
                'data' => ['id' => 'trx-1', 'status' => 'APPROVED', 'status_message' => null],
            ]),
            'sandbox.wompi.co/v1/transactions' => Http::response([
                'data' => ['id' => 'trx-1', 'status' => 'PENDING'],
            ], 201),
        ]);
    }

    public function test_owner_without_active_subscription_is_blocked_from_panel(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);

        $this->actingAs($business->owner)
            ->getJson('/api/dashboard')
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_required');
    }

    public function test_owner_without_price_configured_has_free_access(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => null]);

        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertOk();
    }

    public function test_owner_with_active_subscription_has_access(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create(['business_id' => $business->id, 'price_cents' => 8000000]);

        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertOk();
    }

    public function test_expired_subscription_blocks_access(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->expired()->create(['business_id' => $business->id]);

        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertStatus(402);
    }

    public function test_subscription_endpoints_are_reachable_without_active_subscription(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $this->fakeWompiHappyPath();

        $this->actingAs($business->owner)
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('data.requires_subscription', true)
            ->assertJsonPath('data.has_access', false)
            ->assertJsonPath('data.price_cents', 8000000)
            ->assertJsonPath('data.grace_days', 5);
    }

    public function test_subscribe_creates_payment_source_charges_and_activates(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $this->fakeWompiHappyPath();

        $response = $this->actingAs($business->owner)
            ->postJson('/api/subscription/subscribe', ['card_token' => 'tok_test_visa']);

        $response->assertOk()
            ->assertJsonPath('data.has_access', true)
            ->assertJsonPath('data.subscription.status', 'activa')
            ->assertJsonPath('data.subscription.card_last_four', '4242');

        $subscription = $business->subscription()->first();
        $this->assertSame('activa', $subscription->status);
        $this->assertSame('9911', (string) $subscription->wompi_payment_source_id);
        $this->assertTrue($subscription->current_period_ends_at->isFuture());

        $payment = SubscriptionPayment::first();
        $this->assertSame('APPROVED', $payment->status);
        $this->assertSame(8000000, (int) $payment->amount_cents);

        // Con la suscripción activa, el panel ya responde.
        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertOk();
    }

    public function test_declined_charge_does_not_grant_access(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);

        Http::fake([
            'sandbox.wompi.co/v1/merchants/*' => Http::response([
                'data' => ['presigned_acceptance' => ['acceptance_token' => 'acc_tok', 'permalink' => 'x']],
            ]),
            'sandbox.wompi.co/v1/payment_sources' => Http::response([
                'data' => ['id' => 9911, 'public_data' => ['brand' => 'VISA', 'last_four' => '4242']],
            ], 201),
            'sandbox.wompi.co/v1/transactions/*' => Http::response([
                'data' => ['id' => 'trx-2', 'status' => 'DECLINED', 'status_message' => 'Fondos insuficientes'],
            ]),
            'sandbox.wompi.co/v1/transactions' => Http::response([
                'data' => ['id' => 'trx-2', 'status' => 'PENDING'],
            ], 201),
        ]);

        $this->actingAs($business->owner)
            ->postJson('/api/subscription/subscribe', ['card_token' => 'tok_test_declined'])
            ->assertStatus(422);

        $this->assertSame('vencida', $business->subscription()->first()->status);
        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertStatus(402);
    }

    public function test_cancel_keeps_access_until_period_end_and_resume_reverts(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create(['business_id' => $business->id]);

        $this->actingAs($business->owner)
            ->postJson('/api/subscription/cancel')
            ->assertOk()
            ->assertJsonPath('data.subscription.cancel_at_period_end', true)
            ->assertJsonPath('data.has_access', true);

        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertOk();

        $this->actingAs($business->owner)
            ->postJson('/api/subscription/resume')
            ->assertOk()
            ->assertJsonPath('data.subscription.cancel_at_period_end', false);

        $this->assertFalse($subscription->fresh()->cancel_at_period_end);
    }

    public function test_subscribe_accepts_raw_card_data_and_tokenizes_server_side(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $this->fakeWompiHappyPath();

        $response = $this->actingAs($business->owner)->postJson('/api/subscription/subscribe', [
            'card_number' => '4242424242424242',
            'cvc' => '123',
            'exp_month' => '12',
            'exp_year' => '29',
            'card_holder' => 'Andrés Prueba',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.has_access', true)
            ->assertJsonPath('data.subscription.status', 'activa')
            ->assertJsonPath('data.subscription.payment_method', 'tarjeta');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/tokens/cards')
            && $request['number'] === '4242424242424242');
    }

    public function test_pay_with_nequi_creates_pending_payment_without_redirect(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);

        Http::fake([
            'sandbox.wompi.co/v1/merchants/*' => Http::response([
                'data' => ['presigned_acceptance' => ['acceptance_token' => 'acc_tok', 'permalink' => 'x']],
            ]),
            'sandbox.wompi.co/v1/transactions' => Http::response([
                'data' => ['id' => 'trx-nequi-1', 'status' => 'PENDING'],
            ], 201),
        ]);

        $response = $this->actingAs($business->owner)
            ->postJson('/api/subscription/pay', ['method' => 'nequi', 'phone' => '3001234567']);

        $response->assertOk()
            ->assertJsonPath('redirect_url', null)
            ->assertJsonPath('data.subscription.has_pending_payment', true);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/transactions') || $request->method() !== 'POST') {
                return false;
            }

            $expected = hash('sha256', $request['reference'].'8000000'.'COP'.'test_integrity_secret');

            return $request['payment_method']['type'] === 'NEQUI'
                && $request['payment_method']['phone_number'] === '3001234567'
                && $request['signature'] === $expected
                && $request['acceptance_token'] === 'acc_tok';
        });

        $this->assertSame('PENDING', SubscriptionPayment::where('wompi_transaction_id', 'trx-nequi-1')->value('status'));
        // Sin acceso hasta que Wompi confirme.
        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertStatus(402);
    }

    public function test_pay_with_pse_returns_bank_redirect_url(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);

        Http::fake([
            'sandbox.wompi.co/v1/merchants/*' => Http::response([
                'data' => ['presigned_acceptance' => ['acceptance_token' => 'acc_tok', 'permalink' => 'x']],
            ]),
            'sandbox.wompi.co/v1/transactions/*' => Http::response([
                'data' => [
                    'id' => 'trx-pse-1',
                    'status' => 'PENDING',
                    'payment_method' => ['extra' => ['async_payment_url' => 'https://sandbox.wompi.co/pse/pagar/1']],
                ],
            ]),
            'sandbox.wompi.co/v1/transactions' => Http::response([
                'data' => ['id' => 'trx-pse-1', 'status' => 'PENDING'],
            ], 201),
        ]);

        $response = $this->actingAs($business->owner)->postJson('/api/subscription/pay', [
            'method' => 'pse',
            'user_type' => '0',
            'legal_id_type' => 'CC',
            'legal_id' => '1023456789',
            'financial_institution_code' => '1',
            'full_name' => 'Ana Dueña',
            'phone' => '3001234567',
        ]);

        $response->assertOk()->assertJsonPath('redirect_url', 'https://sandbox.wompi.co/pse/pagar/1');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transactions')
            && $request->method() === 'POST'
            && $request['payment_method']['type'] === 'PSE'
            && $request['payment_method']['financial_institution_code'] === '1'
            && $request['customer_data']['full_name'] === 'Ana Dueña');
    }

    public function test_pse_banks_endpoint_returns_institutions(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);

        Http::fake([
            'sandbox.wompi.co/v1/pse/financial_institutions' => Http::response([
                'data' => [['financial_institution_code' => '1', 'financial_institution_name' => 'Banco que aprueba']],
            ]),
        ]);

        $this->actingAs($business->owner)
            ->getJson('/api/subscription/banks')
            ->assertOk()
            ->assertJsonPath('data.0.financial_institution_code', '1');
    }

    public function test_owner_can_delete_saved_card(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'tarjeta',
        ]);

        $this->actingAs($business->owner)
            ->deleteJson('/api/subscription/card')
            ->assertOk()
            ->assertJsonPath('data.subscription.card_last_four', null)
            ->assertJsonPath('data.subscription.payment_method', 'transferencia')
            // Conserva el acceso: solo cambia cómo pagará el próximo mes.
            ->assertJsonPath('data.has_access', true);

        $subscription->refresh();
        $this->assertNull($subscription->wompi_payment_source_id);
        $this->assertNull($subscription->card_brand);
    }

    public function test_approved_payment_applied_twice_extends_period_only_once(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'status' => 'pendiente',
            'current_period_ends_at' => null,
        ]);
        $payment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'business_id' => $business->id,
            'wompi_transaction_id' => 'trx-doble',
            'amount_cents' => 8000000,
            'currency' => 'COP',
            'status' => 'APPROVED',
        ]);

        $biller = app(\App\Services\Wompi\SubscriptionBiller::class);
        $biller->applyPaymentStatus($payment->fresh());
        $firstPeriodEnd = $subscription->fresh()->current_period_ends_at;

        // Un webhook reenviado no debe volver a extender el periodo.
        $biller->applyPaymentStatus($payment->fresh());

        $this->assertTrue($firstPeriodEnd->equalTo($subscription->fresh()->current_period_ends_at));
    }

    public function test_widget_transaction_confirm_activates_subscription(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'status' => 'pendiente',
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
            'current_period_ends_at' => null,
        ]);

        Http::fake([
            'sandbox.wompi.co/v1/transactions/trx-widget-1' => Http::response([
                'data' => [
                    'id' => 'trx-widget-1',
                    'status' => 'APPROVED',
                    'reference' => 'recepia-sub-'.$subscription->id.'-abc123def456',
                    'amount_in_cents' => 8000000,
                    'currency' => 'COP',
                ],
            ]),
        ]);

        $this->actingAs($business->owner)
            ->postJson('/api/subscription/confirm', ['transaction_id' => 'trx-widget-1'])
            ->assertOk()
            ->assertJsonPath('data.has_access', true)
            ->assertJsonPath('data.subscription.status', 'activa');

        $this->assertSame('APPROVED', SubscriptionPayment::where('wompi_transaction_id', 'trx-widget-1')->value('status'));
        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertOk();
    }

    public function test_widget_confirm_rejects_transaction_of_another_business(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $otherSubscription = Subscription::factory()->create(['status' => 'pendiente']);

        Http::fake([
            'sandbox.wompi.co/v1/transactions/trx-ajena' => Http::response([
                'data' => [
                    'id' => 'trx-ajena',
                    'status' => 'APPROVED',
                    'reference' => 'recepia-sub-'.$otherSubscription->id.'-abc123def456',
                    'amount_in_cents' => 8000000,
                    'currency' => 'COP',
                ],
            ]),
        ]);

        $this->actingAs($business->owner)
            ->postJson('/api/subscription/confirm', ['transaction_id' => 'trx-ajena'])
            ->assertStatus(422);

        $this->assertSame(0, SubscriptionPayment::count());
    }

    public function test_wompi_webhook_registers_widget_transaction_by_reference(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'status' => 'pendiente',
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
            'current_period_ends_at' => null,
        ]);

        $reference = 'recepia-sub-'.$subscription->id.'-webhookref123';
        $timestamp = time();
        $checksum = hash('sha256', 'trx-widget-web'.'APPROVED'.$timestamp.'test_events_secret');

        $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => [
                'id' => 'trx-widget-web',
                'status' => 'APPROVED',
                'reference' => $reference,
                'amount_in_cents' => 8000000,
                'currency' => 'COP',
            ]],
            'timestamp' => $timestamp,
            'signature' => [
                'properties' => ['transaction.id', 'transaction.status'],
                'checksum' => $checksum,
            ],
        ])->assertOk();

        $this->assertSame('APPROVED', SubscriptionPayment::where('wompi_transaction_id', 'trx-widget-web')->value('status'));
        $this->assertSame('activa', $subscription->fresh()->status);
    }

    public function test_recurring_charge_includes_integrity_signature(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'current_period_ends_at' => now()->subHour(),
        ]);

        $this->fakeWompiHappyPath();

        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/transactions') || $request->method() !== 'POST') {
                return false;
            }

            $expected = hash('sha256', $request['reference'].'8000000'.'COP'.'test_integrity_secret');

            return $request['signature'] === $expected;
        });
    }

    public function test_transfer_payment_confirmed_by_polling_grants_access(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'status' => 'pendiente',
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
            'current_period_ends_at' => null,
        ]);
        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'business_id' => $business->id,
            'wompi_transaction_id' => 'trx-poll-1',
            'amount_cents' => 8000000,
            'currency' => 'COP',
            'status' => 'PENDING',
        ]);

        Http::fake([
            'sandbox.wompi.co/v1/transactions/trx-poll-1' => Http::response([
                'data' => ['id' => 'trx-poll-1', 'status' => 'APPROVED', 'status_message' => null],
            ]),
        ]);

        // El GET de la página consulta el estado real en Wompi y lo aplica.
        $this->actingAs($business->owner)
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('data.has_access', true)
            ->assertJsonPath('data.subscription.status', 'activa')
            ->assertJsonPath('data.subscription.has_pending_payment', false);

        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertOk();
    }

    public function test_grace_period_keeps_access_after_period_ends(): void
    {
        config(['recepia.billing.grace_days' => 5]);

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'transferencia',
            'current_period_ends_at' => now()->subDays(2),
        ]);

        // Venció hace 2 días pero la gracia es de 5: sigue entrando.
        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertOk();

        $this->actingAs($business->owner)
            ->getJson('/api/subscription')
            ->assertJsonPath('data.subscription.payment_due', true)
            ->assertJsonPath('data.has_access', true);
    }

    public function test_charge_command_expires_transfer_subscription_after_grace(): void
    {
        config(['recepia.billing.grace_days' => 5]);

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
            'current_period_ends_at' => now()->subDays(6),
        ]);

        Http::fake();

        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        $this->assertSame('vencida', $subscription->fresh()->status);
        Http::assertNothingSent();
        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertStatus(402);
    }

    public function test_charge_command_does_not_expire_transfer_subscription_within_grace(): void
    {
        config(['recepia.billing.grace_days' => 5]);

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
            'current_period_ends_at' => now()->subDay(),
        ]);

        Http::fake();

        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        $this->assertSame('activa', $subscription->fresh()->status);
        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertOk();
    }

    public function test_charge_command_renews_due_subscription(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'current_period_ends_at' => now()->subHour(),
        ]);

        $this->fakeWompiHappyPath();

        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('activa', $subscription->status);
        $this->assertTrue($subscription->current_period_ends_at->isFuture());
        $this->assertSame(1, $subscription->payments()->count());
    }

    public function test_charge_command_finalizes_scheduled_cancellation_without_charging(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'current_period_ends_at' => now()->subHour(),
            'cancel_at_period_end' => true,
        ]);

        Http::fake();

        $this->artisan('recepia:cobrar-suscripciones')->assertSuccessful();

        $this->assertSame('cancelada', $subscription->fresh()->status);
        Http::assertNothingSent();
        $this->actingAs($business->owner)->getJson('/api/dashboard')->assertStatus(402);
    }

    public function test_wompi_webhook_resolves_pending_payment_and_activates(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'status' => 'pendiente',
            'current_period_ends_at' => null,
        ]);
        $payment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'business_id' => $business->id,
            'wompi_transaction_id' => 'trx-web-1',
            'amount_cents' => 8000000,
            'currency' => 'COP',
            'status' => 'PENDING',
        ]);

        $timestamp = time();
        $checksum = hash('sha256', 'trx-web-1'.'APPROVED'.$timestamp.'test_events_secret');

        $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => ['id' => 'trx-web-1', 'status' => 'APPROVED']],
            'timestamp' => $timestamp,
            'signature' => [
                'properties' => ['transaction.id', 'transaction.status'],
                'checksum' => $checksum,
            ],
        ])->assertOk();

        $this->assertSame('APPROVED', $payment->fresh()->status);
        $this->assertSame('activa', $subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->current_period_ends_at->isFuture());
    }

    public function test_wompi_webhook_rejects_invalid_signature(): void
    {
        $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => ['id' => 'x', 'status' => 'APPROVED']],
            'timestamp' => time(),
            'signature' => ['properties' => ['transaction.id'], 'checksum' => 'malo'],
        ])->assertStatus(403);
    }

    public function test_admin_can_set_monthly_price_when_creating_business(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->postJson('/api/admin/businesses', [
            'name' => 'Barbería Paga',
            'type' => 'barberia',
            'status' => 'activo',
            'tone' => 'cercano',
            'monthly_price' => 80000,
            'owner_mode' => 'new',
            'owner_name' => 'Dueño',
            'owner_email' => 'dueno@example.com',
            'owner_password' => 'secreto123',
        ]);

        $response->assertCreated()->assertJsonPath('data.monthly_price', 80000);
        $this->assertSame(8000000, Business::where('name', 'Barbería Paga')->value('monthly_price_cents'));
    }

    public function test_super_admin_is_never_blocked_by_paywall(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->getJson('/api/admin/metrics')->assertOk();
    }
}
