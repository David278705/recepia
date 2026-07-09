<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'wa_id' => '57'.fake()->numerify('##########'),
            'name' => fake()->name(),
            'notes' => null,
        ];
    }
}
