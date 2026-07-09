<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'contact_id',
        'status',
        'last_activity_at',
        'window_expires_at',
        'bot_paused_until',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'window_expires_at' => 'datetime',
            'bot_paused_until' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function isBotAvailable(): bool
    {
        if ($this->status !== 'bot_activo') {
            return false;
        }

        return ! $this->bot_paused_until || $this->bot_paused_until->isPast();
    }
}
