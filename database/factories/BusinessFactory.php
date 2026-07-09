<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'type' => fake()->randomElement(['barberia', 'clinica', 'restaurante', 'otro']),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'timezone' => 'America/Bogota',
            'status' => 'piloto',
            'tone' => fake()->randomElement(['formal', 'cercano']),
            'extra_instructions' => null,
        ];
    }
}
