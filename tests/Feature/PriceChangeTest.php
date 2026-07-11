<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionPriceChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PriceChangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    protected function updatePrice(Business $business, int $priceCop)
    {
        return $this->actingAs($this->admin)->putJson("/api/admin/businesses/{$business->id}", [
            'monthly_price' => $priceCop,
        ]);
    }

    public function test_price_increase_stops_card_auto_renewal_and_notifies_owner(): void
    {
        Notification::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'tarjeta',
        ]);

        $this->updatePrice($business, 100000)->assertOk();

        $subscription->refresh();
        $this->assertTrue($subscription->cancel_at_period_end);
        // El periodo pagado se respeta: sigue activa hasta su fecha.
        $this->assertSame('activa', $subscription->status);
        // El precio congelado no se toca: no se cobrará el nuevo sin aceptación.
        $this->assertSame(8000000, (int) $subscription->price_cents);

        Notification::assertSentTo($business->owner, SubscriptionPriceChangedNotification::class);
    }

    public function test_price_change_with_manual_payment_only_notifies(): void
    {
        Notification::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'transferencia',
            'wompi_payment_source_id' => null,
        ]);

        $this->updatePrice($business, 100000)->assertOk();

        $this->assertFalse($subscription->fresh()->cancel_at_period_end);
        Notification::assertSentTo($business->owner, SubscriptionPriceChangedNotification::class);
    }

    public function test_same_price_does_nothing(): void
    {
        Notification::fake();

        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'tarjeta',
        ]);

        $this->updatePrice($business, 80000)->assertOk();

        $this->assertFalse($subscription->fresh()->cancel_at_period_end);
        Notification::assertNothingSent();
    }

    public function test_resume_after_price_change_adopts_current_price(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 10000000]);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'payment_method' => 'tarjeta',
            'price_cents' => 8000000,
            'cancel_at_period_end' => true,
            'cancelled_at' => now(),
        ]);

        $this->actingAs($business->owner)->postJson('/api/subscription/resume')->assertOk();

        $subscription->refresh();
        $this->assertFalse($subscription->cancel_at_period_end);
        $this->assertSame(10000000, (int) $subscription->price_cents);
    }
}
