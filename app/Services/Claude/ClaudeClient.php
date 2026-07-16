<?php

namespace App\Services\Claude;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente delgado sobre la Messages API de Anthropic (Claude).
 * https://docs.anthropic.com/en/api/messages
 */
class ClaudeClient
{
    protected string $endpoint = 'https://api.anthropic.com/v1/messages';

    protected string $apiVersion = '2023-06-01';

    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $model = null,
    ) {
        $this->apiKey ??= config('services.anthropic.key');
        $this->model ??= config('services.anthropic.model');
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Envía un mensaje a Claude y devuelve el texto de la respuesta.
     */
    public function complete(string $userPrompt, ?string $system = null, int $maxTokens = 1024): string
    {
        $response = $this->send([['role' => 'user', 'content' => $userPrompt]], $system, [], $maxTokens);

        return collect($response['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    /**
     * Envía una conversación completa (con tool use opcional) y devuelve la
     * respuesta cruda de la API (content blocks, usage, stop_reason).
     */
    public function send(array $messages, ?string $system = null, array $tools = [], ?int $maxTokens = null): array
    {
        if (! $this->apiKey) {
            throw new RuntimeException('ANTHROPIC_API_KEY no está configurado.');
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => $maxTokens ?? config('pilo.agent.max_tokens', 1024),
            'messages' => $messages,
        ];

        if ($system) {
            $payload['system'] = $system;
        }

        if ($tools) {
            $payload['tools'] = $tools;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'content-type' => 'application/json',
        ])
            ->timeout(config('pilo.agent.timeout', 20))
            ->post($this->endpoint, $payload);

        $response->throw();

        return $response->json();
    }
}
