<?php

namespace App\Discounts;

use App\Models\Coupon;

/**
 * The outcome of resolving a coupon code against a subtotal. Immutable: a
 * resolution is either valid (carrying the AED-base discount and the coupon it
 * came from) or invalid (carrying a shopper-facing reason).
 */
final class DiscountResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly float $amount = 0.0,
        public readonly ?Coupon $coupon = null,
        public readonly ?string $message = null,
    ) {}

    public static function fail(string $message): self
    {
        return new self(false, 0.0, null, $message);
    }

    public static function ok(float $amount, Coupon $coupon): self
    {
        return new self(true, $amount, $coupon);
    }
}
