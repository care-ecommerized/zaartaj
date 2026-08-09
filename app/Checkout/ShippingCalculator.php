<?php

namespace App\Checkout;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Throwable;

class ShippingCalculator
{
    public function __construct(private readonly ZoneResolver $zones = new ZoneResolver) {}

    /**
     * The delivery charge, in the AED base currency, for an order of this
     * subtotal and weight shipping to this country and district.
     *
     * The destination resolves to an admin-defined zone; the applicable rate
     * within that zone is chosen by method (flat, order-value bracket or weight
     * bracket) and its free-over threshold applied. The district lets Bangladesh
     * split into inside-Dhaka and outside-Dhaka zones — everywhere else prices by
     * country and simply ignores it.
     *
     * Resilient by design: a checkout must never fail because shipping could not
     * be priced, so any gap (no zone, no matching rate, a query error) falls back
     * to the configured default charge.
     */
    public function charge(float $subtotalAed, string $country, ?float $totalWeight = null, ?string $district = null): float
    {
        try {
            $zone = $this->zones->forDestination($country, $district);

            if (! $zone) {
                return $this->fallback();
            }

            $rate = $this->applicableRate($zone, $subtotalAed, $totalWeight);

            if (! $rate) {
                return $this->fallback();
            }

            if ($rate->free_over !== null && $subtotalAed >= (float) $rate->free_over) {
                return 0.0;
            }

            return round((float) $rate->amount, 2);
        } catch (Throwable) {
            return $this->fallback();
        }
    }

    /**
     * Pick the rate that applies to this order within the zone.
     *
     * Rates are considered highest-priority first (ties broken by id), and the
     * first one whose method matches the order is used.
     */
    private function applicableRate(ShippingZone $zone, float $subtotal, ?float $weight): ?ShippingRate
    {
        return $zone->rates
            ->sortBy([['priority', 'desc'], ['id', 'asc']])
            ->first(fn (ShippingRate $rate) => $this->rateMatches($rate, $subtotal, $weight));
    }

    private function rateMatches(ShippingRate $rate, float $subtotal, ?float $weight): bool
    {
        return match ($rate->method) {
            ShippingRate::METHOD_FLAT => true,
            ShippingRate::METHOD_ORDER_VALUE => $this->inBracket($subtotal, $rate),
            ShippingRate::METHOD_WEIGHT => $weight !== null && $this->inBracket($weight, $rate),
            default => false,
        };
    }

    /**
     * Whether a value sits inside the rate's [min, max] bracket. A null bound is
     * open on that side.
     */
    private function inBracket(float $value, ShippingRate $rate): bool
    {
        if ($rate->min_threshold !== null && $value < (float) $rate->min_threshold) {
            return false;
        }

        if ($rate->max_threshold !== null && $value > (float) $rate->max_threshold) {
            return false;
        }

        return true;
    }

    private function fallback(): float
    {
        return (float) config('checkout.shipping.fallback', 80);
    }
}
