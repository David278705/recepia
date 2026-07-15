<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappAccount extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'phone_number_id',
        'waba_id',
        'phone_e164',
        'access_token',
        'two_step_pin',
        'verify_token',
        'verified_name',
        'quality_rating',
        'mode',
        'connection_status',
        'connected_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'two_step_pin' => 'encrypted',
            'connected_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
