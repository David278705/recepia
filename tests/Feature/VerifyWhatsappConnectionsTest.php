<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Notifications\Admin\WhatsappConnectionAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerifyWhatsappConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_degraded_connection_is_marked_and_alerts_admin_once(): void
    {
        Notification::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190]], 401)]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $business = Business::factory()->create();
        $account = WhatsappAccount::factory()->create([
            'business_id' => $business->id,
            'access_token' => 'TOKEN',
            'connection_status' => 'conectado',
        ]);

        $this->artisan('recepia:verificar-conexiones')->assertSuccessful();

        $this->assertSame('error', $account->fresh()->connection_status);
        Notification::assertSentTo($admin, WhatsappConnectionAlert::class);

        // Segunda corrida con la conexión aún caída: no alerta de nuevo.
        Notification::fake();
        $this->artisan('recepia:verificar-conexiones')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_healthy_connection_updates_last_checked_at(): void
    {
        Notification::fake();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'verified_name' => 'Negocio',
            'display_phone_number' => '+57 300 111 2222',
            'quality_rating' => 'GREEN',
            'platform_type' => 'SMB_APP',
        ], 200)]);

        $business = Business::factory()->create();
        $account = WhatsappAccount::factory()->create([
            'business_id' => $business->id,
            'access_token' => 'TOKEN',
            'connection_status' => 'error', // se recupera sola
        ]);

        $this->artisan('recepia:verificar-conexiones')->assertSuccessful();

        $fresh = $account->fresh();
        $this->assertSame('conectado', $fresh->connection_status);
        $this->assertSame('GREEN', $fresh->quality_rating);
        $this->assertNotNull($fresh->last_checked_at);
        Notification::assertNothingSent();
    }
}
