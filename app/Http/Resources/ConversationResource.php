<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'last_activity_at' => $this->last_activity_at,
            'window_expires_at' => $this->window_expires_at,
            'window_open' => $this->window_expires_at && $this->window_expires_at->isFuture(),
            'bot_paused_until' => $this->bot_paused_until,
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'wa_id' => $this->contact->wa_id,
            ]),
            'last_message' => $this->whenLoaded('messages', function () {
                $last = $this->messages->last();

                return $last ? [
                    'content' => $last->content,
                    'origin' => $last->origin,
                    'created_at' => $last->created_at,
                ] : null;
            }),
        ];
    }
}
