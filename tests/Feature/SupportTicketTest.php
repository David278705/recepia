<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketCreatedNotification;
use App\Notifications\SupportTicketRepliedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_ticket_and_admins_are_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)->postJson('/api/support-tickets', [
            'type' => 'error',
            'subject' => 'El bot no responde',
            'message' => 'Desde ayer el bot no contesta los mensajes.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'abierto');

        Notification::assertSentTo($admin, SupportTicketCreatedNotification::class);
    }

    public function test_ticket_type_must_be_valid(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)->postJson('/api/support-tickets', [
            'type' => 'otro',
            'subject' => 'x',
            'message' => 'y',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_user_cannot_see_tickets_of_others(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $other = User::factory()->create(['role' => 'owner']);
        $ticket = SupportTicket::create([
            'user_id' => $other->id,
            'type' => 'queja',
            'subject' => 'Privado',
            'message' => 'Contenido ajeno',
        ]);

        $this->actingAs($user)->getJson("/api/support-tickets/{$ticket->id}")->assertNotFound();
    }

    public function test_admin_reply_marks_ticket_as_answered_and_notifies_user(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create(['role' => 'owner']);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'type' => 'sugerencia',
            'subject' => 'Idea',
            'message' => 'Estaría bueno exportar las citas.',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/support-tickets/{$ticket->id}/replies", [
            'message' => 'Gracias, lo agregamos a la lista.',
        ])->assertCreated();

        $this->assertSame('respondido', $ticket->fresh()->status);
        Notification::assertSentTo($user, SupportTicketRepliedNotification::class);
    }

    public function test_user_cannot_reply_a_closed_ticket_and_reply_reopens(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'type' => 'error',
            'subject' => 'Falla',
            'message' => 'Algo falla',
            'status' => 'cerrado',
        ]);

        $this->actingAs($user)->postJson("/api/support-tickets/{$ticket->id}/replies", [
            'message' => 'Sigue fallando',
        ])->assertUnprocessable();

        $ticket->update(['status' => 'respondido']);

        $this->actingAs($user)->postJson("/api/support-tickets/{$ticket->id}/replies", [
            'message' => 'Sigue fallando',
        ])->assertCreated();

        $this->assertSame('abierto', $ticket->fresh()->status);
    }

    public function test_owner_cannot_access_admin_ticket_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)->getJson('/api/admin/support-tickets')->assertForbidden();
    }
}
