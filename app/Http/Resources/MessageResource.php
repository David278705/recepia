<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'origin' => $this->origin,
            'type' => $this->type,
            'content' => $this->content,
            'delivery_status' => $this->delivery_status,
            'created_at' => $this->created_at,
        ];
    }
}
