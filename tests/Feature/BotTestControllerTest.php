<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotTestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_test_returns_a_reply_without_persisting_side_effects(): void
    {
        $business = Business::factory()->create();
        $service = Service::factory()->create(['business_id' => $business->id]);

        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push([
                'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'agendar_cita', 'input' => [
                    'servicio_id' => $service->id,
                    'inicio' => '2026-08-10 15:00',
                    'nombre_cliente' => 'Prueba',
                ]]],
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 400, 'output_tokens' => 30],
            ])
            ->push([
                'content' => [['type' => 'text', 'text' => 'Listo, cita simulada agendada.']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 420, 'output_tokens' => 15],
            ]),
        ]);

        $response = $this->actingAs($business->owner)->postJson('/api/bot-test', [
            'messages' => [['role' => 'user', 'content' => 'Quiero agendar un corte mañana a las 3pm']],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.reply', 'Listo, cita simulada agendada.');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('agent_logs', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_bot_test_escalation_does_not_create_a_real_escalation(): void
    {
        $business = Business::factory()->create();

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'escalar_a_humano', 'input' => ['motivo' => 'no_sabe']]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 300, 'output_tokens' => 20],
        ])]);

        $response = $this->actingAs($business->owner)->postJson('/api/bot-test', [
            'messages' => [['role' => 'user', 'content' => '¿Hacen cirugías?']],
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('escalations', 0);
    }
}
