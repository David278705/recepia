<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Escalation;
use App\Models\Faq;
use App\Models\Message;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Notifications\AppointmentBookedNotification;
use App\Notifications\ConversationEscalatedNotification;
use App\Services\Claude\ReceptionistAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReceptionistAgentTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;

    protected Contact $contact;

    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200)]);

        $owner = User::factory()->create(['name' => 'Carlos', 'role' => 'owner']);
        $this->business = Business::factory()->create(['user_id' => $owner->id, 'timezone' => 'America/Bogota']);
        WhatsappAccount::factory()->create(['business_id' => $this->business->id]);

        Faq::factory()->create([
            'business_id' => $this->business->id,
            'question' => '¿Hasta qué hora atienden?',
            'answer' => 'Hasta las 7pm.',
        ]);

        BusinessHour::factory()->create(['business_id' => $this->business->id, 'day_of_week' => 1]);

        $this->contact = Contact::factory()->create(['business_id' => $this->business->id, 'name' => null]);
        $this->conversation = Conversation::factory()->create([
            'business_id' => $this->business->id,
            'contact_id' => $this->contact->id,
            'status' => 'bot_activo',
        ]);
    }

    protected function seedCustomerMessage(string $text): Message
    {
        return $this->conversation->messages()->create([
            'business_id' => $this->business->id,
            'direction' => 'in',
            'origin' => 'cliente',
            'type' => 'text',
            'content' => $text,
            'wamid' => 'wamid.IN.'.uniqid(),
            'delivery_status' => 'delivered',
        ]);
    }

    protected function anthropicTextResponse(string $text, int $inputTokens = 500, int $outputTokens = 20): array
    {
        return [
            'id' => 'msg_'.uniqid(),
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
        ];
    }

    protected function anthropicToolUseResponse(string $toolName, array $input, int $inputTokens = 600, int $outputTokens = 40): array
    {
        return [
            'id' => 'msg_'.uniqid(),
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'tool_use', 'id' => 'toolu_'.uniqid(), 'name' => $toolName, 'input' => $input]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
        ];
    }

    public function test_answers_a_faq_directly_with_text(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicTextResponse('Abrimos hasta las 7pm de lunes a sábado.')),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200),
        ]);

        $this->seedCustomerMessage('¿Hasta qué hora atienden?');

        (new ReceptionistAgent)->respond($this->conversation->fresh());

        $reply = Message::where('conversation_id', $this->conversation->id)->where('origin', 'bot')->first();
        $this->assertNotNull($reply);
        $this->assertSame('Abrimos hasta las 7pm de lunes a sábado.', $reply->content);
        $this->assertSame(520, $reply->tokens_used);
        $this->assertNotNull($reply->estimated_cost);

        $this->assertDatabaseCount('agent_logs', 1);
        $this->conversation->refresh();
        $this->assertSame('bot_activo', $this->conversation->status);
    }

    public function test_happy_path_booking_creates_confirmed_appointment(): void
    {
        $service = Service::factory()->create(['business_id' => $this->business->id, 'duration_minutes' => 30]);
        $inicio = now($this->business->timezone)->addDay()->setTime(15, 0)->format('Y-m-d H:i');

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->anthropicToolUseResponse('agendar_cita', [
                    'servicio_id' => $service->id,
                    'inicio' => $inicio,
                    'nombre_cliente' => 'Andrés',
                ]))
                ->push($this->anthropicTextResponse('Listo Andrés, tu cita quedó confirmada para el 6 de julio a las 3:00pm 👍')),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200),
        ]);

        $this->seedCustomerMessage('Quiero agendar un corte mañana a las 3pm, soy Andrés');

        Notification::fake();

        (new ReceptionistAgent)->respond($this->conversation->fresh());

        $appointment = Appointment::where('business_id', $this->business->id)->first();
        $this->assertNotNull($appointment);
        $this->assertSame('confirmada', $appointment->status);
        $this->assertSame('bot', $appointment->origin);
        $this->assertSame($service->id, $appointment->service_id);

        $this->contact->refresh();
        $this->assertSame('Andrés', $this->contact->name);

        Notification::assertSentTo($this->business->owner, AppointmentBookedNotification::class);

        $reply = Message::where('conversation_id', $this->conversation->id)->where('origin', 'bot')->first();
        $this->assertStringContainsString('confirmada', $reply->content);
    }

    public function test_booking_in_the_past_is_rejected(): void
    {
        $service = Service::factory()->create(['business_id' => $this->business->id, 'duration_minutes' => 30]);
        $inicio = now($this->business->timezone)->subHours(2)->format('Y-m-d H:i');

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->anthropicToolUseResponse('agendar_cita', [
                    'servicio_id' => $service->id,
                    'inicio' => $inicio,
                    'nombre_cliente' => 'Andrés',
                ]))
                ->push($this->anthropicTextResponse('Ese horario ya pasó, ¿te sirve mañana a las 3pm?')),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200),
        ]);

        $this->seedCustomerMessage('Agéndame hoy más temprano');

        Notification::fake();

        (new ReceptionistAgent)->respond($this->conversation->fresh());

        $this->assertDatabaseCount('appointments', 0);

        $reply = Message::where('conversation_id', $this->conversation->id)->where('origin', 'bot')->first();
        $this->assertStringContainsString('ya pasó', $reply->content);
    }

    public function test_agent_escalates_when_it_does_not_know_the_answer(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicToolUseResponse('escalar_a_humano', ['motivo' => 'no_sabe'])),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200),
        ]);

        $this->seedCustomerMessage('¿Hacen tratamientos de quimioterapia capilar con células madre?');

        Notification::fake();

        (new ReceptionistAgent)->respond($this->conversation->fresh());

        $this->conversation->refresh();
        $this->assertSame('escalada', $this->conversation->status);
        $this->assertSame(1, Escalation::where('conversation_id', $this->conversation->id)->where('reason', 'no_sabe')->count());

        Notification::assertSentTo($this->business->owner, ConversationEscalatedNotification::class);

        $reply = Message::where('conversation_id', $this->conversation->id)->where('origin', 'bot')->first();
        $this->assertStringContainsString('Carlos', $reply->content);
    }

    public function test_agent_escalates_when_customer_asks_for_a_human(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicToolUseResponse('escalar_a_humano', ['motivo' => 'cliente_lo_pidio'])),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200),
        ]);

        $this->seedCustomerMessage('Quiero hablar con una persona, no con un bot');

        (new ReceptionistAgent)->respond($this->conversation->fresh());

        $this->conversation->refresh();
        $this->assertSame('escalada', $this->conversation->status);
        $this->assertSame(1, Escalation::where('conversation_id', $this->conversation->id)->where('reason', 'cliente_lo_pidio')->count());
    }

    public function test_claude_failure_escalates_and_still_replies_to_the_customer(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => ['message' => 'internal error']], 500),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200),
        ]);

        $this->seedCustomerMessage('Hola');

        (new ReceptionistAgent)->respond($this->conversation->fresh());

        $this->conversation->refresh();
        $this->assertSame('escalada', $this->conversation->status);
        $this->assertSame(1, Escalation::where('conversation_id', $this->conversation->id)->count());

        $reply = Message::where('conversation_id', $this->conversation->id)->where('origin', 'bot')->first();
        $this->assertNotNull($reply, 'El cliente debe recibir una respuesta aunque Claude falle.');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }
}
