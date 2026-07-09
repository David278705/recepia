<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_reports_real_counts(): void
    {
        // Hora fija a media mañana: con la hora real, cerca de medianoche
        // "now()+2h" caería en el día siguiente y el conteo de hoy fallaría.
        $this->travelTo(now('America/Bogota')->setTime(10, 0));

        $business = Business::factory()->create(['timezone' => 'America/Bogota']);
        $contact = Contact::factory()->create(['business_id' => $business->id]);

        Conversation::factory()->create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
            'status' => 'bot_activo',
            'last_activity_at' => now(),
        ]);

        Conversation::factory()->create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
            'status' => 'escalada',
        ]);

        Appointment::factory()->create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
            'status' => 'confirmada',
            'origin' => 'bot',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(2)->addMinutes(30),
        ]);

        Appointment::factory()->create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
            'status' => 'confirmada',
            'origin' => 'bot',
            'starts_at' => now()->addDay()->addHours(2),
            'ends_at' => now()->addDay()->addHours(2)->addMinutes(30),
        ]);

        $response = $this->actingAs($business->owner)->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.appointments_today', 1);
        $response->assertJsonPath('data.appointments_tomorrow', 1);
        $response->assertJsonPath('data.pending_escalations', 1);
        $response->assertJsonPath('data.appointments_booked_by_bot_this_month', 2);
    }

    public function test_dashboard_includes_activity_agenda_and_recent_conversations(): void
    {
        $this->travelTo(now('America/Bogota')->setTime(10, 0));

        $business = Business::factory()->create(['timezone' => 'America/Bogota']);
        $contact = Contact::factory()->create(['business_id' => $business->id, 'name' => 'Ana']);
        $service = Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte']);

        $conversation = Conversation::factory()->create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
            'status' => 'bot_activo',
            'last_activity_at' => now(),
        ]);

        Message::factory()->create([
            'business_id' => $business->id,
            'conversation_id' => $conversation->id,
            'origin' => 'cliente',
            'content' => 'Hola, ¿tienen turno hoy?',
        ]);

        Message::factory()->create([
            'business_id' => $business->id,
            'conversation_id' => $conversation->id,
            'origin' => 'bot',
            'content' => 'Claro, tenemos a las 3 pm.',
            'tokens_used' => 900,
            'estimated_cost' => 0.002,
        ]);

        Appointment::factory()->create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
            'service_id' => $service->id,
            'status' => 'confirmada',
            'origin' => 'bot',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHour()->addMinutes(30),
        ]);

        $response = $this->actingAs($business->owner)->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.bot_messages_this_month', 1);
        $response->assertJsonPath('data.total_contacts', 1);
        $response->assertJsonCount(7, 'data.activity_7d');
        $response->assertJsonPath('data.activity_7d.6.conversations', 1);
        $response->assertJsonPath('data.todays_appointments.0.contact', 'Ana');
        $response->assertJsonPath('data.todays_appointments.0.service', 'Corte');
        $response->assertJsonPath('data.recent_conversations.0.contact', 'Ana');
        $response->assertJsonPath('data.recent_conversations.0.snippet', 'Claro, tenemos a las 3 pm.');
    }

    public function test_admin_metrics_aggregates_cost_per_business(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $business = Business::factory()->create(['status' => 'activo']);
        $contact = Contact::factory()->create(['business_id' => $business->id]);
        $conversation = Conversation::factory()->create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
            'last_activity_at' => now(),
        ]);

        Message::factory()->count(2)->create([
            'business_id' => $business->id,
            'conversation_id' => $conversation->id,
            'origin' => 'bot',
            'tokens_used' => 1000,
            'estimated_cost' => 0.005,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics');

        $response->assertOk();
        $response->assertJsonPath('data.bot_messages_this_month', 2);
        $response->assertJsonPath('data.tokens_this_month', 2000);
        $response->assertJsonPath('data.estimated_cost_this_month', 0.01);
        $response->assertJsonPath('data.businesses.0.tokens_this_month', 2000);
    }

    public function test_admin_system_health_reports_whatsapp_and_queue_state(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $business = Business::factory()->create();
        $business->whatsappAccount()->create([
            'phone_number_id' => '123456',
            'waba_id' => '789',
            'phone_e164' => '+573001234567',
            'access_token' => 'token-de-prueba',
            'verify_token' => 'verify',
            'mode' => 'coexistence',
            'connection_status' => 'conectado',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/system-health');

        $response->assertOk();
        $response->assertJsonPath('data.businesses.0.whatsapp_phone', '+573001234567');
        $response->assertJsonPath('data.businesses.0.whatsapp_connection', 'conectado');
        $response->assertJsonPath('data.queue.pending_jobs', 0);
        $response->assertJsonPath('data.queue.failed_jobs_total', 0);
    }
}
