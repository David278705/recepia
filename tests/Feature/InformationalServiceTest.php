<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Service;
use App\Services\Claude\ReceptionistAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_service_without_duration_and_with_price_note(): void
    {
        $business = Business::factory()->create();

        $this->actingAs($business->owner)->postJson('/api/services', [
            'name' => 'Estampado de camisetas',
            'duration_minutes' => null,
            'price' => 25000,
            'price_note' => 'desde, según diseño',
        ])->assertCreated()
            ->assertJsonPath('data.duration_minutes', null)
            ->assertJsonPath('data.price_note', 'desde, según diseño');
    }

    public function test_agent_refuses_to_check_availability_or_book_informational_services(): void
    {
        $business = Business::factory()->create();
        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => null,
            'active' => true,
        ]);

        $execute = new \ReflectionMethod(ReceptionistAgent::class, 'executeTool');
        $agent = new ReceptionistAgent;

        $availability = $execute->invoke($agent, $business, null, 'consultar_disponibilidad', [
            'servicio_id' => $service->id,
            'fecha' => now()->addDay()->toDateString(),
        ], true);
        $this->assertArrayHasKey('error', $availability);

        $booking = $execute->invoke($agent, $business, null, 'agendar_cita', [
            'servicio_id' => $service->id,
            'inicio' => now()->addDay()->format('Y-m-d 10:00'),
            'nombre_cliente' => 'Ana',
        ], true);
        $this->assertArrayHasKey('error', $booking);
    }

    public function test_prompt_lists_informational_services_with_price_note(): void
    {
        $business = Business::factory()->create();
        Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Domicilios',
            'duration_minutes' => null,
            'price' => null,
            'price_note' => 'según la zona',
            'active' => true,
        ]);

        $method = new \ReflectionMethod(ReceptionistAgent::class, 'buildSystemPrompt');
        $prompt = $method->invoke(new ReceptionistAgent, $business);

        $this->assertStringContainsString('no se agenda por este canal', $prompt);
        $this->assertStringContainsString('según la zona', $prompt);
    }
}
