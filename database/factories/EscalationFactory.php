<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Escalation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Escalation>
 */
class EscalationFactory extends Factory
{
    protected $model = Escalation::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'business_id' => fn (array $attributes) => Conversation::find($attributes['conversation_id'])->business_id,
            'reason' => fake()->randomElement(['no_sabe', 'cliente_lo_pidio', 'molestia', 'keyword']),
            'resolved_at' => null,
        ];
    }
}
