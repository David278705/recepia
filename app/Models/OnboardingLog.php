<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingLog extends Model
{
    protected $fillable = ['business_id', 'step', 'status', 'meta_error_code', 'message'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
