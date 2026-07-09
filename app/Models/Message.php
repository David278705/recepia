<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'conversation_id',
        'business_id',
        'direction',
        'origin',
        'type',
        'content',
        'wamid',
        'delivery_status',
        'tokens_used',
        'estimated_cost',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            if (! $message->business_id && $message->conversation) {
                $message->business_id = $message->conversation->business_id;
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
