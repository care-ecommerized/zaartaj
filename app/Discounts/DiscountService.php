<?php

namespace App\Discounts;

use App\Currency\CurrencyService;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;

/**
 * The coupon authority.
 *
 * Every rule a code must pass — active, in window, within its global and
 * per-user limits, and above its minimum subtotal — is checked here against the
 * database, never the client. The discount is always returned in the AED base
 * currency so the checkout can subtract it from the base subtotal directly.
 */
class DiscountService
{
    public function __construct(private readonly CurrencyService $currencies = new CurrencyService) {}

    /**
     * Resolve a code against an AED subtotal for a given shopper.
     *
     * @param  float  $subtotalAed  The order subtotal in the base currency.
     */
    public function resolve(string $code, float $subtotalAed, ?int $userId, ?string $email): DiscountResult
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return DiscountResult::fail('Enter a promo code.');
        }

        $coupon = Coupon::query()
            ->active()
            ->whereRaw('UPPER(code) = ?', [$code])
            ->first();

        if (! $coupon) {
            return DiscountResult::fail('This promo code is not valid.');
        }

        if (! $coupon->isWindowOpen()) {
            return DiscountResult::fail('This promo code has expired or is not yet active.');
        }

        if (! $coupon->hasGlobalCapacity()) {
            return DiscountResult::fail('This promo code has reached its usage limit.');
        }

        if ($coupon->min_subtotal !== null && $subtotalAed < (float) $coupon->min_subtotal) {
            return DiscountResult::fail('Your order does not reach this promo code\'s minimum.');
        }

        if ($coupon->per_user_limit !== null && $this->timesUsedBy($coupon, $userId, $email) >= $coupon->per_user_limit) {
            return DiscountResult::fail('You have already used this promo code.');
        }

        $amount = $coupon->type === Coupon::TYPE_PERCENT
            ? round($subtotalAed * (float) $coupon->value / 100, 2)
            : $this->currencies->toBase((float) $coupon->value, $coupon->currency ?: $this->currencies->base());

        // A discount never exceeds the subtotal (shipping is charged in full).
        $amount = max(0.0, min($amount, $subtotalAed));

        return DiscountResult::ok(round($amount, 2), $coupon);
    }

    /**
     * How many times this shopper has already redeemed the coupon, counted by
     * user id or email. With neither identifier the count is zero, so a fully
     * anonymous guest is never blocked by a per-user limit.
     */
    private function timesUsedBy(Coupon $coupon, ?int $userId, ?string $email): int
    {
        if ($userId === null && ($email === null || $email === '')) {
            return 0;
        }

        return $coupon->redemptions()
            ->where(function (Builder $query) use ($userId, $email) {
                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }

                if ($email !== null && $email !== '') {
                    $query->orWhere('email', $email);
                }
            })
            ->count();
    }
}
