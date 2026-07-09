<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['timezone' => 'America/Bogota']);
        $this->service = Service::factory()->create(['business_id' => $this->business->id, 'duration_minutes' => 30]);
    }

    public function test_owner_can_create_a_manual_appointment_with_a_new_contact(): void
    {
        $response = $this->actingAs($this->business->owner)->postJson('/api/appointments', [
            'service_id' => $this->service->id,
            'starts_at' => '2026-08-10 15:00',
            'contact_mode' => 'new',
            'contact_name' => 'Laura',
            'contact_wa_id' => '573001112233',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', ['business_id' => $this->business->id, 'status' => 'confirmada', 'origin' => 'panel']);
        $this->assertDatabaseHas('contacts', ['business_id' => $this->business->id, 'wa_id' => '573001112233', 'name' => 'Laura']);
    }

    public function test_owner_can_reschedule_an_appointment(): void
    {
        $contact = Contact::factory()->create(['business_id' => $this->business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'contact_id' => $contact->id,
            'service_id' => $this->service->id,
            'starts_at' => '2026-08-10 15:00:00',
            'ends_at' => '2026-08-10 15:30:00',
        ]);

        $response = $this->actingAs($this->business->owner)->putJson("/api/appointments/{$appointment->id}", [
            'starts_at' => '2026-08-11 10:00',
        ]);

        $response->assertOk();
        $appointment->refresh();
        $this->assertSame('10:00:00', $appointment->starts_at->setTimezone('America/Bogota')->format('H:i:s'));
    }

    public function test_owner_can_cancel_an_appointment(): void
    {
        $contact = Contact::factory()->create(['business_id' => $this->business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'contact_id' => $contact->id,
            'status' => 'confirmada',
        ]);

        $this->actingAs($this->business->owner)
            ->postJson("/api/appointments/{$appointment->id}/cancel")
            ->assertOk();

        $this->assertSame('cancelada', $appointment->fresh()->status);
    }

    public function test_index_only_returns_appointments_within_the_requested_week(): void
    {
        $contact = Contact::factory()->create(['business_id' => $this->business->id]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'contact_id' => $contact->id,
            'starts_at' => '2026-08-10 10:00:00',
            'ends_at' => '2026-08-10 10:30:00',
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'contact_id' => $contact->id,
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at' => '2026-09-01 10:30:00',
        ]);

        $response = $this->actingAs($this->business->owner)
            ->getJson('/api/appointments?week_start=2026-08-09');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
