<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Notifications\ConversationEscalatedNotification;
use App\Services\Claude\ReceptionistAgent;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Procesa un payload crudo del webhook de WhatsApp: identifica el negocio,
 * carga/crea contacto y conversación, guarda cada mensaje (con idempotencia
 * por wamid) y decide si el bot debe responder.
 *
 * Nota sobre echoes de coexistence: asumimos que Meta entrega los mensajes
 * que el dueño envía desde su propia app con `from` igual al número del
 * negocio (en vez del número del cliente). Este supuesto debe verificarse
 * contra un payload real de Meta al conectar el primer negocio piloto — si
 * el formato real difiere, ajustar únicamente `isOwnerEcho()`.
 */
class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected array $payload) {}

    public function handle(): void
    {
        foreach ($this->payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $metadata = $value['metadata'] ?? [];
                $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach ($value['messages'] ?? [] as $message) {
                    $this->processMessage($metadata, $message, $contacts->get($message['from'] ?? null));
                }
            }
        }
    }

    protected function processMessage(array $metadata, array $message, ?array $contactProfile): void
    {
        $phoneNumberId = $metadata['phone_number_id'] ?? null;
        $account = $phoneNumberId ? WhatsappAccount::where('phone_number_id', $phoneNumberId)->first() : null;

        if (! $account) {
            Log::warning('Pilo: mensaje de WhatsApp para un phone_number_id sin negocio conectado.', ['phone_number_id' => $phoneNumberId]);

            return;
        }

        $wamid = $message['id'] ?? null;

        if (! $wamid) {
            Log::warning('Pilo: mensaje de WhatsApp sin wamid, se descarta.', ['business_id' => $account->business_id]);

            return;
        }

        if (Message::where('wamid', $wamid)->exists()) {
            return; // idempotencia: ya procesado (reintento de Meta)
        }

        $isEcho = $this->isOwnerEcho($message, $account);
        $contactWaId = $isEcho ? ($message['to'] ?? null) : ($message['from'] ?? null);

        if (! $contactWaId) {
            Log::warning('Pilo: no se pudo determinar el contacto del mensaje (echo sin destinatario).', ['business_id' => $account->business_id, 'wamid' => $wamid]);

            return;
        }

        $contact = Contact::firstOrCreate(
            ['business_id' => $account->business_id, 'wa_id' => $contactWaId],
            ['name' => $contactProfile['profile']['name'] ?? null]
        );

        $conversation = Conversation::where('business_id', $account->business_id)
            ->where('contact_id', $contact->id)
            ->where('status', '!=', 'cerrada')
            ->latest('last_activity_at')
            ->first() ?? Conversation::create([
                'business_id' => $account->business_id,
                'contact_id' => $contact->id,
                'status' => 'bot_activo',
            ]);

        $savedMessage = $conversation->messages()->create([
            'business_id' => $account->business_id,
            'direction' => $isEcho ? 'out' : 'in',
            'origin' => $isEcho ? 'dueno_app' : 'cliente',
            'type' => $this->mapMessageType($message['type'] ?? null),
            'content' => $this->extractContent($message),
            'wamid' => $wamid,
            'delivery_status' => 'delivered',
        ]);

        $conversation->update([
            'last_activity_at' => now(),
            'window_expires_at' => now()->addHours(24),
        ]);

        if ($isEcho) {
            $conversation->update([
                'bot_paused_until' => now()->addMinutes(config('pilo.whatsapp.bot_pause_minutes')),
            ]);

            return; // nunca se trata un echo del dueño como entrada del cliente
        }

        $this->markAsRead($account, $wamid);

        if (! $conversation->fresh()->isBotAvailable()) {
            return;
        }

        if ($savedMessage->type !== 'text') {
            $this->handleUnsupportedMessage($account, $conversation, $contact);

            return;
        }

        if ($this->botMessageLimitReached($account, $conversation)) {
            $this->escalateForMessageLimit($account, $conversation, $contact);

            return;
        }

        $this->invokeAgent($conversation);
    }

    protected function isOwnerEcho(array $message, WhatsappAccount $account): bool
    {
        $from = $this->normalizePhone($message['from'] ?? null);
        $businessNumber = $this->normalizePhone($account->phone_e164);

        return $from !== '' && $from === $businessNumber;
    }

    protected function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    protected function mapMessageType(?string $type): string
    {
        $supported = ['text', 'image', 'audio', 'video', 'document', 'location', 'interactive'];

        return in_array($type, $supported, true) ? $type : 'other';
    }

    protected function extractContent(array $message): ?string
    {
        return match ($message['type'] ?? null) {
            'text' => $message['text']['body'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? null,
            default => null,
        };
    }

    protected function markAsRead(WhatsappAccount $account, string $wamid): void
    {
        try {
            (new WhatsAppService($account))->markAsRead($wamid);
        } catch (\Throwable $e) {
            Log::warning('Pilo: no se pudo marcar como leído el mensaje de WhatsApp.', ['wamid' => $wamid, 'error' => $e->getMessage()]);
        }
    }

    protected function handleUnsupportedMessage(WhatsappAccount $account, Conversation $conversation, Contact $contact): void
    {
        $action = config('pilo.whatsapp.unsupported_message_action');

        if ($action === 'escalate') {
            $conversation->update(['status' => 'escalada']);
            $conversation->escalations()->create([
                'business_id' => $account->business_id,
                'reason' => 'no_sabe',
            ]);

            return;
        }

        try {
            $reply = config('pilo.whatsapp.unsupported_message_reply');
            $response = (new WhatsAppService($account))->sendText($contact->wa_id, $reply);

            $conversation->messages()->create([
                'business_id' => $account->business_id,
                'direction' => 'out',
                'origin' => 'bot',
                'type' => 'text',
                'content' => $reply,
                'wamid' => $response->json('messages.0.id'),
                'delivery_status' => 'sent',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Pilo: no se pudo enviar la respuesta de tipo no soportado.', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * ¿El bot ya gastó su cupo de respuestas en esta conversación durante las
     * últimas 24 h? El límite lo fija el admin por negocio (default 20).
     */
    protected function botMessageLimitReached(WhatsappAccount $account, Conversation $conversation): bool
    {
        $limit = (int) ($account->business?->daily_message_limit ?? 20);

        if ($limit <= 0) {
            return false; // 0 = sin límite
        }

        return $conversation->messages()
            ->where('origin', 'bot')
            ->where('created_at', '>=', now()->subDay())
            ->count() >= $limit;
    }

    /**
     * Cupo agotado: la conversación pasa al dueño (misma mecánica que
     * cualquier escalación — el bot queda silenciado hasta que la retome) y
     * se le avisa al cliente que una persona lo atenderá.
     */
    protected function escalateForMessageLimit(WhatsappAccount $account, Conversation $conversation, Contact $contact): void
    {
        $business = $account->business;

        $conversation->update(['status' => 'escalada']);
        $conversation->escalations()->create([
            'business_id' => $business->id,
            'reason' => 'limite_mensajes',
        ]);

        $business->owner?->notify(new ConversationEscalatedNotification($conversation->fresh(), 'limite_mensajes'));

        try {
            $ownerName = $business->owner?->name ?? 'el equipo';
            $reply = "Ya le aviso a {$ownerName} para que continúe contigo personalmente, te contacta pronto 👍";
            $response = (new WhatsAppService($account))->sendText($contact->wa_id, $reply);

            $conversation->messages()->create([
                'business_id' => $business->id,
                'direction' => 'out',
                'origin' => 'bot',
                'type' => 'text',
                'content' => $reply,
                'wamid' => $response->json('messages.0.id'),
                'delivery_status' => 'sent',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Pilo: no se pudo avisar al cliente del límite de mensajes.', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);
        }
    }

    protected function invokeAgent(Conversation $conversation): void
    {
        (new ReceptionistAgent)->respond($conversation->fresh());
    }
}
