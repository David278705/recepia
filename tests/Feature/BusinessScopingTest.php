<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Frontera de seguridad multi-tenant: un `owner` nunca debe poder ver ni
 * resolver por ID datos de un negocio que no es el suyo, sin importar el
 * modelo. `super_admin` y contextos sin usuario autenticado (consola,
 * seeders) no deben quedar filtrados.
 */
class BusinessScopingTest extends TestCase
{
    use RefreshDatabase;

    protected Business $businessA;

    protected Business $businessB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessA = Business::factory()->create();
        $this->businessB = Business::factory()->create();
    }

    public function test_owner_only_sees_services_from_their_own_business(): void
    {
        $serviceA = Service::factory()->create(['business_id' => $this->businessA->id]);
        $serviceB = Service::factory()->create(['business_id' => $this->businessB->id]);

        $this->actingAs($this->businessA->owner);

        $this->assertSame(1, Service::count());
        $this->assertTrue(Service::first()->is($serviceA));
        $this->assertNull(Service::find($serviceB->id));
    }

    public function test_owner_only_sees_contacts_from_their_own_business(): void
    {
        $contactA = Contact::factory()->create(['business_id' => $this->businessA->id]);
        $contactB = Contact::factory()->create(['business_id' => $this->businessB->id]);

        $this->actingAs($this->businessA->owner);

        $this->assertSame(1, Contact::count());
        $this->assertTrue(Contact::first()->is($contactA));
        $this->assertNull(Contact::find($contactB->id));
    }

    public function test_owner_only_sees_conversations_and_messages_from_their_own_business(): void
    {
        $conversationA = Conversation::factory()->create(['business_id' => $this->businessA->id]);
        $conversationB = Conversation::factory()->create(['business_id' => $this->businessB->id]);

        $messageA = Message::factory()->create(['conversation_id' => $conversationA->id, 'business_id' => $this->businessA->id]);
        Message::factory()->create(['conversation_id' => $conversationB->id, 'business_id' => $this->businessB->id]);

        $this->actingAs($this->businessA->owner);

        $this->assertSame(1, Conversation::count());
        $this->assertNull(Conversation::find($conversationB->id));

        $this->assertSame(1, Message::count());
        $this->assertTrue(Message::first()->is($messageA));
    }

    public function test_super_admin_sees_data_across_all_businesses(): void
    {
        Service::factory()->create(['business_id' => $this->businessA->id]);
        Service::factory()->create(['business_id' => $this->businessB->id]);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin);

        $this->assertSame(2, Service::count());
    }

    public function test_unauthenticated_context_is_not_scoped(): void
    {
        Service::factory()->create(['business_id' => $this->businessA->id]);
        Service::factory()->create(['business_id' => $this->businessB->id]);

        $this->assertSame(2, Service::count());
    }
}
