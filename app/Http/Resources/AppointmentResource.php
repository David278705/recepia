<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $business = $this->business;

        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at->setTimezone($business->timezone),
            'ends_at' => $this->ends_at->setTimezone($business->timezone),
            'status' => $this->status,
            'origin' => $this->origin,
            'notes' => $this->notes,
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'wa_id' => $this->contact->wa_id,
            ]),
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ] : null),
        ];
    }
}
