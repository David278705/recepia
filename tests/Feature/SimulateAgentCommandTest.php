<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimulateAgentCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function anthropicText(string $text): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 50, 'output_tokens' => 10],
        ];
    }

    public function test_simulates_a_message_and_prints_the_reply_without_persisting_data(): void
    {
        Storage::fake('local');
        $business = Business::factory()->create(['slug' => 'barberia-el-corte']);

        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicText('Hola, ¿en qué te ayudo?'))]);

        $this->artisan('recepia:simular', ['mensaje' => 'Hola'])
            ->expectsOutputToContain('Hola, ¿en qué te ayudo?')
            ->assertSuccessful();

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_reset_starts_a_new_session(): void
    {
        Storage::fake('local');
        $business = Business::factory()->create(['slug' => 'barberia-el-corte']);

        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicText('Respuesta 1'))]);
        $this->artisan('recepia:simular', ['mensaje' => 'Primero'])->assertSuccessful();

        Storage::assertExists("recepia-simular/{$business->id}.json");
        $sessionAfterFirst = json_decode(Storage::get("recepia-simular/{$business->id}.json"), true);
        $this->assertCount(2, $sessionAfterFirst);

        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicText('Respuesta 2'))]);
        $this->artisan('recepia:simular', ['mensaje' => 'Otra vez', '--reset' => true])->assertSuccessful();

        $sessionAfterReset = json_decode(Storage::get("recepia-simular/{$business->id}.json"), true);
        $this->assertCount(2, $sessionAfterReset);
        $this->assertSame('Otra vez', $sessionAfterReset[0]['content']);
    }

    public function test_fails_gracefully_for_an_unknown_business(): void
    {
        $this->artisan('recepia:simular', ['mensaje' => 'Hola', '--business' => 'no-existe'])
            ->assertFailed();
    }
}
