<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\OnboardingLog;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Notifications\Admin\WhatsappConnectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmbeddedSignupTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.app_id' => '123456',
            'services.meta.app_secret' => 'secret',
            'services.meta.graph_version' => 'v23.0',
            'services.meta.es_config_id' => '999',
        ]);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();
    }

    protected function fakeGraph(array $overrides = []): void
    {
        Http::fake(array_merge([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'BIZ_TOKEN'], 200),
            'graph.facebook.com/*/WABA1/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/PN1?*' => Http::response([
                'verified_name' => 'Barbería El Corte',
                'display_phone_number' => '+57 300 111 2222',
                'quality_rating' => 'GREEN',
                'platform_type' => 'SMB_APP',
            ], 200),
            'graph.facebook.com/*/PN1/register' => Http::response(['success' => true], 200),
        ], $overrides));
    }

    protected function completePayload(array $extra = []): array
    {
        return array_merge([
            'code' => 'AUTH_CODE',
            'phone_number_id' => 'PN1',
            'waba_id' => 'WABA1',
            'business_id' => $this->business->id,
        ], $extra);
    }

    public function test_happy_path_coexistence_provisions_account_and_skips_register(): void
    {
        Notification::fake();
        $this->fakeGraph();

        $this->actingAs($this->admin)
            ->postJson('/api/whatsapp/onboarding/complete', $this->completePayload())
            ->assertOk()
            ->assertJsonPath('data.mode', 'coexistence')
            ->assertJsonPath('data.phone', '+573001112222');

        $account = $this->business->fresh()->whatsappAccount;
        $this->assertSame('conectado', $account->connection_status);
        $this->assertSame('BIZ_TOKEN', $account->access_token); // desencriptado por el cast
        $this->assertSame('coexistence', $account->mode);
        $this->assertNotNull($account->connected_at);

        // En coexistencia NO se llama /register.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/register'));

        $this->assertSame('skipped', OnboardingLog::where('step', 'registro_numero')->value('status'));
        Notification::assertSentTo($this->admin, WhatsappConnectedNotification::class);
    }

    public function test_dedicated_number_registers_with_pin(): void
    {
        Notification::fake();
        $this->fakeGraph([
            'graph.facebook.com/*/PN1?*' => Http::response([
                'verified_name' => 'Negocio',
                'display_phone_number' => '+57 300 111 2222',
                'platform_type' => 'CLOUD_API',
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson('/api/whatsapp/onboarding/complete', $this->completePayload())
            ->assertOk()
            ->assertJsonPath('data.mode', 'dedicado');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/PN1/register'));
        $this->assertNotNull($this->business->fresh()->whatsappAccount->two_step_pin);
    }

    public function test_invalid_code_fails_with_clear_message_and_logs(): void
    {
        Notification::fake();
        $this->fakeGraph([
            'graph.facebook.com/*/oauth/access_token*' => Http::response([
                'error' => ['code' => 100, 'message' => 'Invalid verification code'],
            ], 400),
        ]);

        $this->actingAs($this->admin)
            ->postJson('/api/whatsapp/onboarding/complete', $this->completePayload())
            ->assertUnprocessable()
            ->assertJsonPath('meta_error_code', '100');

        $log = OnboardingLog::where('step', 'canje_token')->first();
        $this->assertSame('error', $log->status);
        Notification::assertNothingSent();
    }

    public function test_failed_webhook_subscription_aborts(): void
    {
        $this->fakeGraph([
            'graph.facebook.com/*/WABA1/subscribed_apps' => Http::response(['error' => ['code' => 10, 'message' => 'No permission']], 403),
        ]);

        $this->actingAs($this->admin)
            ->postJson('/api/whatsapp/onboarding/complete', $this->completePayload())
            ->assertUnprocessable();

        $this->assertNull($this->business->fresh()->whatsappAccount);
    }

    public function test_existing_connection_requires_overwrite_confirmation(): void
    {
        Notification::fake();
        WhatsappAccount::factory()->create([
            'business_id' => $this->business->id,
            'phone_number_id' => 'OLD_PN',
            'access_token' => 'OLD_TOKEN',
        ]);

        $this->fakeGraph();

        $this->actingAs($this->admin)
            ->postJson('/api/whatsapp/onboarding/complete', $this->completePayload())
            ->assertStatus(409)
            ->assertJsonPath('requires_confirmation', true);

        $this->actingAs($this->admin)
            ->postJson('/api/whatsapp/onboarding/complete', $this->completePayload(['overwrite' => true]))
            ->assertOk();

        $this->assertSame('PN1', $this->business->fresh()->whatsappAccount->phone_number_id);
    }

    public function test_retry_is_idempotent(): void
    {
        Notification::fake();
        $this->fakeGraph();

        $this->actingAs($this->admin)->postJson('/api/whatsapp/onboarding/complete', $this->completePayload())->assertOk();
        $this->actingAs($this->admin)->postJson('/api/whatsapp/onboarding/complete', $this->completePayload())->assertOk();

        $this->assertSame(1, WhatsappAccount::where('business_id', $this->business->id)->count());
    }

    public function test_owner_without_token_cannot_complete(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->postJson('/api/whatsapp/onboarding/complete', $this->completePayload())
            ->assertForbidden();
    }

    public function test_signed_link_redirects_with_token_that_authorizes_completion(): void
    {
        Notification::fake();
        $this->fakeGraph();

        $signed = URL::temporarySignedRoute('whatsapp.connect', now()->addHours(48), ['business' => $this->business->id]);

        $response = $this->get($signed)->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/whatsapp/onboarding/complete', [
            'code' => 'AUTH_CODE',
            'phone_number_id' => 'PN1',
            'waba_id' => 'WABA1',
            'onboarding_token' => $query['token'],
        ])->assertOk();
    }

    public function test_unsigned_or_expired_access_is_rejected(): void
    {
        // Sin firma → 403.
        $this->get("/connect/{$this->business->id}")->assertForbidden();

        // Token expirado → 403 en el POST.
        $expired = Crypt::encrypt(['business_id' => $this->business->id, 'expires_at' => now()->subMinute()->timestamp]);

        $this->postJson('/api/whatsapp/onboarding/complete', [
            'code' => 'X',
            'phone_number_id' => 'PN1',
            'waba_id' => 'WABA1',
            'onboarding_token' => $expired,
        ])->assertForbidden();
    }
}
