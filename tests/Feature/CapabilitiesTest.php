<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\CustomerRequest;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Notifications\CustomerRequestReceivedNotification;
use App\Services\Claude\ReceptionistAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function agentTools(Business $business): array
    {
        $method = new \ReflectionMethod(ReceptionistAgent::class, 'toolDefinitions');

        return collect($method->invoke(new ReceptionistAgent, $business))->pluck('name')->all();
    }

    public function test_default_capabilities_expose_scheduling_tools(): void
    {
        $business = Business::factory()->create(); // capabilities null = ['agendar']

        $this->assertSame(['consultar_disponibilidad', 'agendar_cita', 'escalar_a_humano'], $this->agentTools($business));
        $this->assertTrue($business->hasCapability('agendar'));
    }

    public function test_capabilities_control_available_tools(): void
    {
        $business = Business::factory()->create(['capabilities' => ['pedidos', 'cotizar']]);

        $this->assertSame(['tomar_pedido', 'registrar_solicitud', 'escalar_a_humano'], $this->agentTools($business));
        $this->assertFalse($business->hasCapability('agendar'));
    }

    public function test_tomar_pedido_creates_customer_request_and_notifies_owner(): void
    {
        Notification::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200)]);

        $business = Business::factory()->create(['capabilities' => ['pedidos']]);
        WhatsappAccount::factory()->create(['business_id' => $business->id]);
        $contact = Contact::factory()->create(['business_id' => $business->id, 'name' => null]);
        $conversation = Conversation::factory()->create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
        ]);

        $method = new \ReflectionMethod(ReceptionistAgent::class, 'executeTool');
        $result = $method->invoke(new ReceptionistAgent, $business, $conversation, 'tomar_pedido', [
            'items' => [['nombre' => 'Hamburguesa doble', 'cantidad' => 2], ['nombre' => 'Gaseosa', 'cantidad' => 1, 'nota' => 'sin hielo']],
            'nombre_cliente' => 'Laura',
            'entrega' => 'domicilio',
            'direccion' => 'Cra 10 # 20-30',
        ], false);

        $this->assertTrue($result['registrado']);

        $request = CustomerRequest::first();
        $this->assertSame('pedido', $request->type);
        $this->assertSame('nueva', $request->status);
        $this->assertCount(2, $request->payload['items']);
        $this->assertSame('Laura', $contact->fresh()->name);

        Notification::assertSentTo($business->owner, CustomerRequestReceivedNotification::class);
    }

    public function test_dry_run_does_not_persist_requests(): void
    {
        $business = Business::factory()->create(['capabilities' => ['cotizar']]);

        $method = new \ReflectionMethod(ReceptionistAgent::class, 'executeTool');
        $result = $method->invoke(new ReceptionistAgent, $business, null, 'registrar_solicitud', [
            'resumen' => 'Cotización de 100 camisetas estampadas',
            'nombre_cliente' => 'Pedro',
        ], true);

        $this->assertTrue($result['simulado']);
        $this->assertSame(0, CustomerRequest::count());
    }

    public function test_owner_lists_and_updates_own_requests_only(): void
    {
        $business = Business::factory()->create();
        $other = Business::factory()->create();

        $mine = CustomerRequest::create([
            'business_id' => $business->id,
            'contact_id' => Contact::factory()->create(['business_id' => $business->id])->id,
            'type' => 'cotizacion',
            'payload' => ['resumen' => 'Algo'],
        ]);
        $foreign = CustomerRequest::create([
            'business_id' => $other->id,
            'contact_id' => Contact::factory()->create(['business_id' => $other->id])->id,
            'type' => 'pedido',
            'payload' => ['items' => []],
        ]);

        $this->actingAs($business->owner)->getJson('/api/customer-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->actingAs($business->owner)
            ->putJson("/api/customer-requests/{$mine->id}/status", ['status' => 'atendida'])
            ->assertOk()
            ->assertJsonPath('data.status', 'atendida');

        $this->actingAs($business->owner)
            ->putJson("/api/customer-requests/{$foreign->id}/status", ['status' => 'cerrada'])
            ->assertNotFound();
    }

    public function test_admin_can_set_capabilities(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $business = Business::factory()->create();

        $this->actingAs($admin)->putJson("/api/admin/businesses/{$business->id}", [
            'capabilities' => ['pedidos'],
        ])->assertOk()
            ->assertJsonPath('data.capabilities', ['pedidos']);

        $this->actingAs($admin)->putJson("/api/admin/businesses/{$business->id}", [
            'capabilities' => ['inventada'],
        ])->assertUnprocessable();
    }
}
