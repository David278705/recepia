<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    protected $model = BusinessHour::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'active' => true,
        ];
    }
}
