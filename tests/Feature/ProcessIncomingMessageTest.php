<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessIncomingMessageTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;

    protected WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT1']]], 200),
            // El agente (Fase 4) se invoca para mensajes de texto soportados;
            // lo mockeamos aquí para que este test se quede enfocado en el
            // procesamiento del webhook, no en la lógica del agente.
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Respuesta de prueba.']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);

        $this->business = Business::factory()->create();
        $this->account = WhatsappAccount::factory()->create([
            'business_id' => $this->business->id,
            'phone_number_id' => '109876543210',
            'phone_e164' => '+573001112222', // número del negocio
        ]);
    }

    protected function payloadFor(array $message, ?array $contactProfile = null): array
    {
        return [
            'entry' => [
                [
                    'id' => $this->account->waba_id,
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['phone_number_id' => $this->account->phone_number_id],
                                'contacts' => $contactProfile ? [$contactProfile] : [],
                                'messages' => [$message],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_creates_contact_conversation_and_message_for_new_customer_text(): void
    {
        $payload = $this->payloadFor(
            ['from' => '573009998888', 'id' => 'wamid.IN1', 'type' => 'text', 'text' => ['body' => 'Hola, ¿tienen turno?']],
            ['wa_id' => '573009998888', 'profile' => ['name' => 'Andrés']]
        );

        (new ProcessIncomingMessage($payload))->handle();

        $contact = Contact::where('business_id', $this->business->id)->where('wa_id', '573009998888')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Andrés', $contact->name);

        $conversation = Conversation::where('contact_id', $contact->id)->first();
        $this->assertNotNull($conversation);
        $this->assertSame('bot_activo', $conversation->status);

        $message = Message::where('wamid', 'wamid.IN1')->first();
        $this->assertNotNull($message);
        $this->assertSame('in', $message->direction);
        $this->assertSame('cliente', $message->origin);
        $this->assertSame('Hola, ¿tienen turno?', $message->content);
    }

    public function test_duplicate_wamid_is_not_processed_twice(): void
    {
        $payload = $this->payloadFor(['from' => '573009998888', 'id' => 'wamid.DUP', 'type' => 'text', 'text' => ['body' => 'Hola']]);

        (new ProcessIncomingMessage($payload))->handle();
        (new ProcessIncomingMessage($payload))->handle();

        $this->assertSame(1, Message::where('wamid', 'wamid.DUP')->count());
    }

    public function test_owner_echo_is_stored_as_dueno_app_and_pauses_the_bot(): void
    {
        $contact = Contact::factory()->create(['business_id' => $this->business->id, 'wa_id' => '573009998888']);
        $conversation = Conversation::factory()->create([
            'business_id' => $this->business->id,
            'contact_id' => $contact->id,
            'status' => 'bot_activo',
        ]);

        $payload = $this->payloadFor([
            'from' => '573001112222', // número del negocio = echo del dueño
            'to' => '573009998888',
            'id' => 'wamid.ECHO1',
            'type' => 'text',
            'text' => ['body' => 'Ya te atiendo yo directamente'],
        ]);

        (new ProcessIncomingMessage($payload))->handle();

        $message = Message::where('wamid', 'wamid.ECHO1')->first();
        $this->assertNotNull($message);
        $this->assertSame('out', $message->direction);
        $this->assertSame('dueno_app', $message->origin);

        $conversation->refresh();
        $this->assertNotNull($conversation->bot_paused_until);
        $this->assertTrue($conversation->bot_paused_until->isFuture());
        $this->assertFalse($conversation->isBotAvailable());
    }

    public function test_unsupported_message_type_sends_courtesy_reply_by_default(): void
    {
        $payload = $this->payloadFor(['from' => '573009998888', 'id' => 'wamid.IMG1', 'type' => 'image']);

        (new ProcessIncomingMessage($payload))->handle();

        $conversation = Conversation::where('business_id', $this->business->id)->first();
        $this->assertSame('bot_activo', $conversation->status);

        $reply = Message::where('conversation_id', $conversation->id)->where('origin', 'bot')->first();
        $this->assertNotNull($reply);
        $this->assertStringContainsString('texto', $reply->content);
    }

    public function test_unsupported_message_type_escalates_when_configured(): void
    {
        Config::set('pilo.whatsapp.unsupported_message_action', 'escalate');

        $payload = $this->payloadFor(['from' => '573009998888', 'id' => 'wamid.LOC1', 'type' => 'location']);

        (new ProcessIncomingMessage($payload))->handle();

        $conversation = Conversation::where('business_id', $this->business->id)->first();
        $this->assertSame('escalada', $conversation->status);
        $this->assertSame(1, $conversation->escalations()->count());
    }
}
