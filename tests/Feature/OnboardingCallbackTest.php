<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Notifications\Admin\WhatsappConnectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OnboardingCallbackTest extends TestCase
{
    use RefreshDatabase;

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

        User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();
    }

    protected function validState(array $overrides = []): string
    {
        return Crypt::encrypt(array_merge([
            'business_id' => $this->business->id,
            'expires_at' => now()->addHour()->timestamp,
        ], $overrides));
    }

    protected function fakeGraphWithDiscovery(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'BIZ_TOKEN'], 200),
            // El redirect no trae los IDs: se descubren con el token.
            'graph.facebook.com/*/debug_token*' => Http::response([
                'data' => ['granular_scopes' => [
                    ['scope' => 'whatsapp_business_management', 'target_ids' => ['WABA9']],
                ]],
            ], 200),
            'graph.facebook.com/*/WABA9/phone_numbers*' => Http::response([
                'data' => [['id' => 'PN9', 'display_phone_number' => '+57 300 999 8888']],
            ], 200),
            'graph.facebook.com/*/WABA9/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/PN9?*' => Http::response([
                'verified_name' => 'Negocio Redirect',
                'display_phone_number' => '+57 300 999 8888',
                'quality_rating' => 'GREEN',
                'platform_type' => 'SMB_APP',
            ], 200),
        ]);
    }

    public function test_callback_discovers_assets_and_provisions(): void
    {
        Notification::fake();
        $this->fakeGraphWithDiscovery();

        $this->get('/whatsapp/onboarding/callback?code=AUTH_CODE&state='.urlencode($this->validState()))
            ->assertRedirect()
            ->assertRedirectContains('/connect-whatsapp?status=success');

        $account = $this->business->fresh()->whatsappAccount;
        $this->assertSame('PN9', $account->phone_number_id);
        $this->assertSame('WABA9', $account->waba_id);
        $this->assertSame('conectado', $account->connection_status);
        $this->assertSame('coexistence', $account->mode);

        // El canje del code por redirect debe incluir el mismo redirect_uri.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth/access_token')
            && str_contains($request->url(), 'redirect_uri='));

        Notification::assertSentTo(User::superAdmins()->first(), WhatsappConnectedNotification::class);
    }

    public function test_callback_rejects_invalid_or_expired_state(): void
    {
        Http::fake();

        $this->get('/whatsapp/onboarding/callback?code=X&state=manipulado')
            ->assertRedirectContains('/connect-whatsapp?status=error');

        $expired = $this->validState(['expires_at' => now()->subMinute()->timestamp]);

        $this->get('/whatsapp/onboarding/callback?code=X&state='.urlencode($expired))
            ->assertRedirectContains('/connect-whatsapp?status=error');

        Http::assertNothingSent();
        $this->assertNull($this->business->fresh()->whatsappAccount);
    }

    public function test_callback_handles_meta_error_and_missing_code(): void
    {
        Http::fake();

        $this->get('/whatsapp/onboarding/callback?error=access_denied&error_description=El+usuario+cancelo')
            ->assertRedirectContains('/connect-whatsapp?status=error');

        $this->get('/whatsapp/onboarding/callback?state='.urlencode($this->validState()))
            ->assertRedirectContains('/connect-whatsapp?status=error');

        Http::assertNothingSent();
    }

    public function test_admin_can_generate_oauth_link_with_state(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/businesses/{$this->business->id}/connect-link/oauth")
            ->assertOk();

        $url = $response->json('data.url');
        $this->assertStringContainsString('facebook.com/v23.0/dialog/oauth', $url);
        $this->assertStringContainsString('response_type=code', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $payload = Crypt::decrypt($query['state']);
        $this->assertSame($this->business->id, $payload['business_id']);
    }
}
