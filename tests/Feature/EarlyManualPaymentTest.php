<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarlyManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_payment_is_rejected_while_period_is_paid(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'current_period_ends_at' => now()->addWeeks(2),
        ]);

        $this->actingAs($business->owner)->postJson('/api/subscription/pay', [
            'method' => 'nequi',
            'phone' => '3001234567',
        ])->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'ya está pagado'));
    }

    public function test_manual_payment_is_allowed_when_period_expired_within_grace(): void
    {
        $business = Business::factory()->create(['monthly_price_cents' => 8000000]);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'current_period_ends_at' => now()->subDay(),
        ]);

        // Pasa el candado del periodo vigente: falla después, al hablar con
        // Wompi (no configurado en tests), no con el 422 de "ya está pagado".
        $response = $this->actingAs($business->owner)->postJson('/api/subscription/pay', [
            'method' => 'nequi',
            'phone' => '3001234567',
        ]);

        $this->assertNotSame('Tu mes ya está pagado', str($response->json('message') ?? '')->limit(21)->value());
    }
}
