<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'business_id' => fn (array $attributes) => Conversation::find($attributes['conversation_id'])->business_id,
            'direction' => fake()->randomElement(['in', 'out']),
            'origin' => fake()->randomElement(['cliente', 'bot']),
            'type' => 'text',
            'content' => fake()->sentence(),
            'wamid' => fake()->unique()->uuid(),
            'delivery_status' => 'delivered',
        ];
    }
}
