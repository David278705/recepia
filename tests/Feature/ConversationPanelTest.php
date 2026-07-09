<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversationPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;

    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200)]);

        $this->business = Business::factory()->create();
        WhatsappAccount::factory()->create(['business_id' => $this->business->id]);

        $contact = Contact::factory()->create(['business_id' => $this->business->id]);
        $this->conversation = Conversation::factory()->create([
            'business_id' => $this->business->id,
            'contact_id' => $contact->id,
            'status' => 'bot_activo',
            'window_expires_at' => now()->addHours(10),
        ]);
    }

    public function test_owner_can_send_a_manual_message_when_the_window_is_open(): void
    {
        $response = $this->actingAs($this->business->owner)
            ->postJson("/api/conversations/{$this->conversation->id}/messages", ['content' => 'Ya casi llego']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'origin' => 'dueno_panel',
            'content' => 'Ya casi llego',
        ]);
    }

    public function test_owner_cannot_send_a_manual_message_when_the_window_expired(): void
    {
        $this->conversation->update(['window_expires_at' => now()->subHour()]);

        $response = $this->actingAs($this->business->owner)
            ->postJson("/api/conversations/{$this->conversation->id}/messages", ['content' => 'Hola']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_take_over_pauses_the_bot_and_return_to_bot_resumes_it(): void
    {
        $this->actingAs($this->business->owner)
            ->postJson("/api/conversations/{$this->conversation->id}/take-over")
            ->assertOk();

        $this->conversation->refresh();
        $this->assertSame('escalada', $this->conversation->status);
        $this->assertFalse($this->conversation->isBotAvailable());

        $this->actingAs($this->business->owner)
            ->postJson("/api/conversations/{$this->conversation->id}/return-to-bot")
            ->assertOk();

        $this->conversation->refresh();
        $this->assertSame('bot_activo', $this->conversation->status);
        $this->assertNull($this->conversation->bot_paused_until);
        $this->assertTrue($this->conversation->isBotAvailable());
    }

    public function test_owner_cannot_see_or_reply_to_another_businesss_conversation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherContact = Contact::factory()->create(['business_id' => $otherBusiness->id]);
        $otherConversation = Conversation::factory()->create([
            'business_id' => $otherBusiness->id,
            'contact_id' => $otherContact->id,
        ]);

        $this->actingAs($this->business->owner)
            ->getJson("/api/conversations/{$otherConversation->id}")
            ->assertStatus(404);
    }

    public function test_index_lists_conversations_with_last_message(): void
    {
        $this->conversation->messages()->create([
            'business_id' => $this->business->id,
            'direction' => 'in',
            'origin' => 'cliente',
            'type' => 'text',
            'content' => 'Hola',
            'wamid' => 'wamid.LIST1',
            'delivery_status' => 'delivered',
        ]);

        $response = $this->actingAs($this->business->owner)->getJson('/api/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.last_message.content', 'Hola');
    }
}
