<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'type',
        'address',
        'phone',
        'timezone',
        'status',
        'monthly_price_cents',
        'tone',
        'agent_model',
        'extra_instructions',
    ];

    protected static function booted(): void
    {
        static::creating(function (Business $business) {
            if (! $business->slug) {
                $business->slug = static::uniqueSlugFor($business->name);
            }
        });
    }

    protected static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'negocio';
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function whatsappAccount(): HasOne
    {
        return $this->hasOne(WhatsappAccount::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * ¿Este negocio necesita una suscripción activa para usar el panel?
     * NULL en el precio = sin cobro (negocio piloto o cortesía).
     */
    public function requiresSubscription(): bool
    {
        return $this->monthly_price_cents !== null && $this->monthly_price_cents > 0;
    }

    public function hasActiveSubscription(): bool
    {
        if (! $this->requiresSubscription()) {
            return true;
        }

        return (bool) $this->subscription?->grantsAccess();
    }
}
