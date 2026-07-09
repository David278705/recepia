<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'status',
        'payment_method',
        'price_cents',
        'currency',
        'wompi_payment_source_id',
        'card_brand',
        'card_last_four',
        'current_period_ends_at',
        'cancel_at_period_end',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Da acceso al panel: está activa y no se ha agotado el plazo para pagar.
     * Tras vencer el periodo hay unos días de gracia (configurables) para
     * renovar; una cancelación programada corta el acceso justo al fin del
     * periodo, sin gracia.
     */
    public function grantsAccess(): bool
    {
        return $this->status === 'activa' && (bool) $this->accessUntil()?->isFuture();
    }

    /**
     * Fecha límite real de acceso: fin del periodo pagado más los días de
     * gracia para renovar.
     */
    public function accessUntil(): ?\Illuminate\Support\Carbon
    {
        if (! $this->current_period_ends_at) {
            return null;
        }

        if ($this->cancel_at_period_end) {
            return $this->current_period_ends_at;
        }

        return $this->current_period_ends_at->copy()->addDays(static::graceDays());
    }

    /**
     * El periodo pagado ya venció y corre el plazo de gracia para pagar.
     */
    public function isPaymentDue(): bool
    {
        return $this->status === 'activa'
            && ! $this->cancel_at_period_end
            && $this->current_period_ends_at !== null
            && $this->current_period_ends_at->isPast();
    }

    public static function graceDays(): int
    {
        return max(0, (int) config('recepia.billing.grace_days', 5));
    }
}
