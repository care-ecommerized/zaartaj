<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code a shopper can apply at checkout.
 *
 * Coupons are validated and priced entirely server-side (see App\Discounts\
 * DiscountService); the client only ever sends a code. Percent codes come off
 * the AED subtotal; fixed codes carry a flat amount defined in `currency`
 * (null = the AED base) which is converted to base at resolve time.
 */
class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'type',
        'value',
        'currency',
        'min_subtotal',
        'starts_at',
        'ends_at',
        'usage_limit',
        'per_user_limit',
        'times_used',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'times_used' => 'integer',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Whether "now" sits inside the coupon's active window. An unset bound is
     * open on that side, so a coupon with neither date is always in window.
     */
    public function isWindowOpen(): bool
    {
        $now = now();

        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Whether the global usage limit still has room. A null limit is unlimited.
     */
    public function hasGlobalCapacity(): bool
    {
        return $this->usage_limit === null || $this->times_used < $this->usage_limit;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
