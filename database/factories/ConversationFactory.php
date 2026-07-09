<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'contact_id' => fn (array $attributes) => Contact::factory()->create(['business_id' => $attributes['business_id']])->id,
            'status' => 'bot_activo',
            'last_activity_at' => now(),
            'window_expires_at' => now()->addHours(24),
        ];
    }
}
