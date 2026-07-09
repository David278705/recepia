<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'address' => $this->address,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'monthly_price' => $this->monthly_price_cents !== null ? intdiv($this->monthly_price_cents, 100) : null,
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription ? [
                'status' => $this->subscription->status,
                'current_period_ends_at' => $this->subscription->current_period_ends_at?->toIso8601String(),
                'cancel_at_period_end' => $this->subscription->cancel_at_period_end,
            ] : null),
            'tone' => $this->tone,
            'agent_model' => $this->agent_model,
            'extra_instructions' => $this->extra_instructions,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null),
            'whatsapp_account' => $this->whenLoaded('whatsappAccount', fn () => $this->whatsappAccount ? [
                'phone_number_id' => $this->whatsappAccount->phone_number_id,
                'waba_id' => $this->whatsappAccount->waba_id,
                'phone_e164' => $this->whatsappAccount->phone_e164,
                'mode' => $this->whatsappAccount->mode,
                'connection_status' => $this->whatsappAccount->connection_status,
            ] : null),
            'pending_escalations_count' => $this->pending_escalations_count ?? null,
            'messages_this_month_count' => $this->messages_this_month_count ?? null,
            'cost_this_month' => $this->cost_this_month !== null ? round((float) $this->cost_this_month, 4) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
