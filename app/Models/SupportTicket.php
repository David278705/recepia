<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const TYPES = ['error', 'queja', 'sugerencia'];

    public const STATUSES = ['abierto', 'respondido', 'cerrado'];

    protected $fillable = ['user_id', 'type', 'subject', 'message', 'status'];

    protected $attributes = ['status' => 'abierto'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class)->orderBy('created_at');
    }
}
