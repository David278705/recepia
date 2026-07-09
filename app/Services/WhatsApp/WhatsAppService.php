<?php

namespace App\Services\WhatsApp;

use App\Models\Business;
use App\Models\WhatsappAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente sobre la WhatsApp Business Cloud API para un negocio concreto:
 * cada negocio tiene su propio phone_number_id y token de acceso
 * (whatsapp_accounts), nunca configuración global.
 */
class WhatsAppService
{
    public function __construct(protected WhatsappAccount $account) {}

    public static function forBusiness(Business $business): self
    {
        $account = $business->whatsappAccount;

        if (! $account) {
            throw new RuntimeException("El negocio #{$business->id} no tiene una cuenta de WhatsApp conectada.");
        }

        return new self($account);
    }

    public function sendText(string $to, string $body): Response
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    /**
     * Botones de respuesta rápida (máx. 3, límite de la API).
     *
     * @param  array<string, string>  $buttons  id => título
     */
    public function sendButtons(string $to, string $bodyText, array $buttons): Response
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => [
                    'buttons' => collect($buttons)
                        ->map(fn (string $title, string $id) => [
                            'type' => 'reply',
                            'reply' => ['id' => $id, 'title' => $title],
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }

    /**
     * Lista interactiva de opciones (para más de 3 alternativas, ej. horarios
     * disponibles).
     *
     * @param  array<int, array{title: string, rows: array<int, array{id: string, title: string, description?: string}>}>  $sections
     */
    public function sendList(string $to, string $bodyText, string $buttonText, array $sections): Response
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'action' => [
                    'button' => $buttonText,
                    'sections' => $sections,
                ],
            ],
        ]);
    }

    public function markAsRead(string $wamid): Response
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $wamid,
        ]);
    }

    protected function post(array $payload): Response
    {
        $response = Http::withToken($this->account->access_token)
            ->post($this->endpoint(), $payload);

        $response->throw();

        return $response;
    }

    protected function endpoint(): string
    {
        $version = config('services.whatsapp.graph_version');

        return "https://graph.facebook.com/{$version}/{$this->account->phone_number_id}/messages";
    }
}
