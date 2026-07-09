<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+1 week');

        return [
            'business_id' => Business::factory(),
            'contact_id' => fn (array $attributes) => Contact::factory()->create(['business_id' => $attributes['business_id']])->id,
            'service_id' => fn (array $attributes) => Service::factory()->create(['business_id' => $attributes['business_id']])->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+30 minutes'),
            'status' => 'propuesta',
            'origin' => 'bot',
            'notes' => null,
        ];
    }
}
