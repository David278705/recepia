<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Notifications\ConversationEscalatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DailyMessageLimitTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;

    protected WhatsappAccount $account;

    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT-LIMIT']]], 200),
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Respuesta de prueba.']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);

        $this->business = Business::factory()->create(['daily_message_limit' => 3]);
        $this->account = WhatsappAccount::factory()->create([
            'business_id' => $this->business->id,
            'phone_number_id' => '109876543210',
            'phone_e164' => '+573001112222',
        ]);

        $contact = Contact::create(['business_id' => $this->business->id, 'wa_id' => '573009998888', 'name' => 'Cliente']);
        $this->conversation = Conversation::create([
            'business_id' => $this->business->id,
            'contact_id' => $contact->id,
            'status' => 'bot_activo',
        ]);
    }

    protected function incoming(string $wamid): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $this->account->phone_number_id],
                        'contacts' => [['wa_id' => '573009998888', 'profile' => ['name' => 'Cliente']]],
                        'messages' => [['from' => '573009998888', 'id' => $wamid, 'type' => 'text', 'text' => ['body' => 'Hola']]],
                    ],
                ]],
            ]],
        ];
    }

    protected function seedBotMessages(int $count, $createdAt): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->conversation->messages()->create([
                'business_id' => $this->business->id,
                'direction' => 'out',
                'origin' => 'bot',
                'type' => 'text',
                'content' => "Respuesta {$i}",
                'wamid' => "wamid.SEED-{$createdAt}-{$i}",
                'delivery_status' => 'sent',
            ]);

            // created_at no es fillable: se fija directo sobre el modelo.
            $this->conversation->messages()->latest('id')->first()
                ->forceFill(['created_at' => $createdAt])->save();
        }
    }

    public function test_conversation_escalates_when_bot_limit_is_reached(): void
    {
        Notification::fake();

        $this->seedBotMessages(3, now()->subHour());

        (new ProcessIncomingMessage($this->incoming('wamid.LIMIT1')))->handle();

        $this->conversation->refresh();
        $this->assertSame('escalada', $this->conversation->status);
        $this->assertTrue($this->conversation->escalations()->where('reason', 'limite_mensajes')->exists());

        Notification::assertSentTo($this->business->owner, ConversationEscalatedNotification::class);

        // Al cliente se le avisó que lo atenderá una persona.
        $this->assertTrue(
            $this->conversation->messages()->where('origin', 'bot')->where('content', 'like', '%te contacta pronto%')->exists()
        );
    }

    public function test_old_bot_messages_outside_24h_do_not_count(): void
    {
        Notification::fake();

        $this->seedBotMessages(3, now()->subDays(2));

        (new ProcessIncomingMessage($this->incoming('wamid.LIMIT2')))->handle();

        $this->assertSame('bot_activo', $this->conversation->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_zero_limit_means_unlimited(): void
    {
        Notification::fake();

        $this->business->update(['daily_message_limit' => 0]);
        $this->seedBotMessages(10, now()->subHour());

        (new ProcessIncomingMessage($this->incoming('wamid.LIMIT3')))->handle();

        $this->assertSame('bot_activo', $this->conversation->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_admin_can_set_limit_and_default_is_20(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->assertSame(20, (int) Business::factory()->create()->fresh()->daily_message_limit);

        $this->actingAs($admin)->putJson("/api/admin/businesses/{$this->business->id}", [
            'daily_message_limit' => 50,
        ])->assertOk()
            ->assertJsonPath('data.daily_message_limit', 50);
    }
}
