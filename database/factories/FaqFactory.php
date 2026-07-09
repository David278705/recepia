<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'active' => true,
        ];
    }
}
