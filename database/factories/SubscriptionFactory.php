<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'status' => 'activa',
            'price_cents' => 8000000, // $80.000 COP
            'currency' => 'COP',
            'wompi_payment_source_id' => (string) fake()->randomNumber(5),
            'card_brand' => 'VISA',
            'card_last_four' => (string) fake()->numberBetween(1000, 9999),
            'current_period_ends_at' => now()->addMonth(),
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(['status' => 'vencida', 'current_period_ends_at' => now()->subDay()]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelada',
            'cancel_at_period_end' => true,
            'cancelled_at' => now(),
            'current_period_ends_at' => now()->subDay(),
        ]);
    }
}
