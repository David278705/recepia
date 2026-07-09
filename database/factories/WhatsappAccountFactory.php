<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\WhatsappAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappAccount>
 */
class WhatsappAccountFactory extends Factory
{
    protected $model = WhatsappAccount::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'phone_number_id' => fake()->numerify('##########'),
            'waba_id' => fake()->numerify('##########'),
            'phone_e164' => '+57'.fake()->numerify('##########'),
            'access_token' => fake()->sha256(),
            'verify_token' => fake()->uuid(),
            'mode' => 'coexistence',
            'connection_status' => 'pendiente',
        ];
    }
}
