<?php

namespace App\Currency;

use App\Models\Currency;
use Illuminate\Support\Collection;

/**
 * The single money authority.
 *
 * Every base-to-presentment conversion and every rounding decision in the app
 * flows through here, reading the `currencies` table (not config) so rates and
 * decimal precision have exactly one source of truth. Base is AED; a rate is
 * "units of the target per 1 AED", so converting *out* of the base multiplies.
 */
class CurrencyService
{
    /**
     * Convert an amount in the base currency (AED) into $target.
     *
     * Same-currency is a no-op beyond rounding to the base's precision. Rounding
     * is HALF_UP so the customer is never charged a fraction less than owed.
     *
     * @throws CurrencyException When $target is missing, inactive, or rate <= 0.
     */
    public function convert(float $aed, string $target): float
    {
        $currency = $this->currency($target);

        if ($target === $this->base()) {
            return round($aed, $currency->decimals, PHP_ROUND_HALF_UP);
        }

        return round($aed * (float) $currency->rate_to_base, $currency->decimals, PHP_ROUND_HALF_UP);
    }

    /**
     * Convert an amount expressed in $currency back into the base (AED).
     *
     * The inverse of convert(): a rate is "units of $currency per 1 AED", so an
     * amount in $currency divides by that rate to reach the base. Same-currency
     * is a no-op beyond rounding. Used to express a fixed coupon — whose value is
     * defined in its own currency — as a base-currency discount.
     *
     * @throws CurrencyException When $currency is missing, inactive, or rate <= 0.
     */
    public function toBase(float $amount, string $currency): float
    {
        if ($currency === $this->base()) {
            return round($amount, 2, PHP_ROUND_HALF_UP);
        }

        return round($amount / (float) $this->currency($currency)->rate_to_base, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Human-facing money string, e.g. "AED 1,364.00" or "KWD 12.345".
     *
     * TODO(locale): honour $locale for Arabic-Indic digits and RTL grouping. For
     * now formatting is English with the ISO code as the symbol.
     */
    public function format(float $amount, string $code, ?string $locale = null): string
    {
        $decimals = $this->decimalsFor($code);

        return $code.' '.number_format($amount, $decimals, '.', ',');
    }

    /**
     * The base currency code (the one everything is priced and stored in).
     */
    public function base(): string
    {
        return config('payment.currency');
    }

    /**
     * The currencies a shopper may be charged in.
     *
     * @return Collection<int, Currency>
     */
    public function active(): Collection
    {
        return Currency::query()->active()->get();
    }

    /**
     * How many minor-unit digits $code prints (2 for most, 3 for KWD/BHD/OMR).
     *
     * @throws CurrencyException
     */
    public function decimalsFor(string $code): int
    {
        return $this->currency($code)->decimals;
    }

    /**
     * Load an active, positively-rated currency or refuse to guess.
     *
     * @throws CurrencyException
     */
    private function currency(string $code): Currency
    {
        $currency = Currency::query()->whereKey($code)->first();

        if (! $currency) {
            throw new CurrencyException("Currency [{$code}] is not defined.");
        }

        if (! $currency->is_active) {
            throw new CurrencyException("Currency [{$code}] is inactive.");
        }

        if ((float) $currency->rate_to_base <= 0) {
            throw new CurrencyException("Currency [{$code}] has no positive exchange rate.");
        }

        return $currency;
    }
}
