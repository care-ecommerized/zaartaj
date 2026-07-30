<?php

namespace App\Models;

use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lightweight trace of a checkout-in-progress.
 *
 * Captured (debounced) the moment a shopper enters contact details, so an
 * abandonment before the order is submitted still leaves something recoverable.
 * This is deliberately NOT an Order: the Order is created only at full submit.
 */
class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_RECOVERED = 'recovered';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'token',
        'user_id',
        'email',
        'phone',
        'currency',
        'locale',
        'cart',
        'subtotal',
        'status',
        'recovered_order_id',
        'last_activity_at',
        'reminder_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cart' => 'array',
            'subtotal' => 'decimal:2',
            'last_activity_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recoveredOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'recovered_order_id');
    }

    /**
     * Sessions still in progress (never submitted, never swept).
     *
     * @param  Builder<CheckoutSession>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Open sessions untouched since $threshold — candidates for a reminder.
     *
     * @param  Builder<CheckoutSession>  $query
     */
    public function scopeStale(Builder $query, \DateTimeInterface $threshold): void
    {
        $query->where('last_activity_at', '<', $threshold);
    }
}
