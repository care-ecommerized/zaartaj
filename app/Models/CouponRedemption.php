<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single use of a coupon against an order. One row is written per redeemed
 * order, in the same transaction that places the order, so the ledger and the
 * coupon's times_used counter never drift.
 */
class CouponRedemption extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coupon_id',
        'order_id',
        'user_id',
        'email',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
