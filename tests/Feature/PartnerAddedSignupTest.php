<?php

namespace Tests\Feature;

use App\Jobs\ProcessPartnerSignup;
use App\Models\Business;
use App\Models\User;
use App\Notifications\Admin\OrphanSignupNotification;
use App\Notifications\Admin\WhatsappConnectedNotification;
use App\Services\Meta\EmbeddedSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PartnerAddedSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.app_id' => '123456',
            'services.meta.app_secret' => 'secret',
            'services.meta.graph_version' => 'v23.0',
            'services.meta.system_user_token' => 'SYS_TOKEN',
            'services.whatsapp.app_secret' => 'whsecret',
        ]);

        User::factory()->create(['role' => 'super_admin']);
    }

    protected function fakeGraph(): void
    {
        Http::fake([
            'graph.facebook.com/*/OWNER1/system_user_access_tokens' => Http::response(['business_token' => 'BIZ_TOKEN'], 200),
            'graph.facebook.com/*/WABA5/phone_numbers*' => Http::response([
                'data' => [['id' => 'PN5', 'display_phone_number' => '+57 302 472 0171']],
            ], 200),
            'graph.facebook.com/*/WABA5/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/PN5?*' => Http::response([
                'verified_name' => 'Barbería El Corte',
                'display_phone_number' => '+57 302 472 0171',
                'quality_rating' => 'GREEN',
                'platform_type' => 'SMB_APP',
            ], 200),
        ]);
    }

    public function test_partner_added_matches_business_by_phone_and_provisions(): void
    {
        Notification::fake();
        $this->fakeGraph();

        // El admin creó el negocio solo con el celular.
        $business = Business::factory()->create(['phone' => '3024720171']);

        (new ProcessPartnerSignup('WABA5', 'OWNER1'))->handle(app(EmbeddedSignupService::class));

        $account = $business->fresh()->whatsappAccount;
        $this->assertNotNull($account);
        $this->assertSame('PN5', $account->phone_number_id);
        $this->assertSame('WABA5', $account->waba_id);
        $this->assertSame('conectado', $account->connection_status);
        $this->assertSame('coexistence', $account->mode);
        $this->assertSame('BIZ_TOKEN', $account->access_token);

        Notification::assertSentTo(User::superAdmins()->first(), WhatsappConnectedNotification::class);
    }

    public function test_phone_matching_tolerates_country_code_and_formatting(): void
    {
        $this->fakeGraph();

        $service = app(EmbeddedSignupService::class);

        $withIndicative = Business::factory()->create(['phone' => '+57 302 472 0171']);
        $this->assertTrue($service->matchBusinessByPhone('+57 302-472-0171')?->is($withIndicative));

        $withIndicative->update(['phone' => '3024720171']);
        $this->assertTrue($service->matchBusinessByPhone('+573024720171')?->is($withIndicative));

        $this->assertNull($service->matchBusinessByPhone('+573009999999'));
    }

    public function test_orphan_signup_alerts_admin(): void
    {
        Notification::fake();
        $this->fakeGraph();

        Business::factory()->create(['phone' => '3001112222']); // no coincide

        (new ProcessPartnerSignup('WABA5', 'OWNER1'))->handle(app(EmbeddedSignupService::class));

        Notification::assertSentTo(User::superAdmins()->first(), OrphanSignupNotification::class);
    }

    public function test_webhook_dispatches_partner_signup_job(): void
    {
        Bus::fake();

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'APP_BM',
                'changes' => [[
                    'field' => 'account_update',
                    'value' => [
                        'event' => 'PARTNER_ADDED',
                        'waba_info' => ['waba_id' => 'WABA5', 'owner_business_id' => 'OWNER1'],
                    ],
                ]],
            ]],
        ]);

        $signature = 'sha256='.hash_hmac('sha256', $payload, 'whsecret');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        Bus::assertDispatched(ProcessPartnerSignup::class);
    }
}
