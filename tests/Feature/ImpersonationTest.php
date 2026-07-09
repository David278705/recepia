<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Impersonar depende de la sesión (Sanctum stateful); en producción
        // esto ya está activo porque el SPA autentica por cookie. En tests,
        // hay que simular un origen "frontend" para que arranque la sesión.
        $this->withHeader('Referer', 'http://localhost');
    }

    public function test_super_admin_can_impersonate_an_owner_and_return(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $business = Business::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/businesses/{$business->id}/impersonate")
            ->assertOk()
            ->assertJsonPath('user.id', $business->owner->id);

        // actingAs() fija el usuario del guard para el resto del test, así
        // que verificamos el efecto real (sesión + guard 'web') en vez de
        // encadenar otra petición HTTP que actingAs volvería a pisar.
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->assertSame($business->owner->id, Auth::guard('web')->id());

        $this->postJson('/api/stop-impersonating')
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id);

        $this->assertNull(session('impersonator_id'));
        $this->assertSame($admin->id, Auth::guard('web')->id());
    }

    public function test_owner_cannot_impersonate(): void
    {
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        $this->actingAs($business->owner)
            ->postJson("/api/admin/businesses/{$otherBusiness->id}/impersonate")
            ->assertStatus(403);
    }
}
