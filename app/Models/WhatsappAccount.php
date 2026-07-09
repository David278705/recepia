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
        'verify_token',
        'mode',
        'connection_status',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
        ];
    }
}
