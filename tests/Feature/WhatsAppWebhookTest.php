<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.whatsapp.verify_token', 'test-verify-token');
        Config::set('services.whatsapp.app_secret', 'test-app-secret');
    }

    public function test_verify_returns_challenge_with_correct_token(): void
    {
        $response = $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=test-verify-token&hub_challenge=12345');

        $response->assertStatus(200)->assertSee('12345');
    }

    public function test_verify_rejects_wrong_token(): void
    {
        $response = $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345');

        $response->assertStatus(403);
    }

    public function test_handle_rejects_request_without_signature(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/webhooks/whatsapp', ['object' => 'whatsapp_business_account']);

        $response->assertStatus(403);
        Queue::assertNotPushed(ProcessIncomingMessage::class);
    }

    public function test_handle_rejects_request_with_invalid_signature(): void
    {
        Queue::fake();

        $payload = ['object' => 'whatsapp_business_account'];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => 'sha256=invalid',
        ]);

        $response->assertStatus(403);
        Queue::assertNotPushed(ProcessIncomingMessage::class);
    }

    public function test_handle_accepts_request_with_valid_signature_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

        $response = $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(200);
        Queue::assertPushed(ProcessIncomingMessage::class);
    }

    public function test_handle_is_rate_limited_per_ip(): void
    {
        Config::set('recepia.whatsapp.webhook_rate_limit', 2);
        Queue::fake();

        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');
        $headers = ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], $headers, $body)->assertStatus(200);
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], $headers, $body)->assertStatus(200);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], $headers, $body)->assertStatus(429);
    }
}
