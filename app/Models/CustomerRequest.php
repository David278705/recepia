<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRequest extends Model
{
    public const TYPES = ['pedido', 'cotizacion'];

    public const STATUSES = ['nueva', 'atendida', 'cerrada'];

    protected $fillable = ['business_id', 'contact_id', 'conversation_id', 'type', 'payload', 'status'];

    protected $attributes = ['status' => 'nueva'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
