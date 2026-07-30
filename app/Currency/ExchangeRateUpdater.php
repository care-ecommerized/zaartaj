<?php

namespace App\Currency;

use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refresh presentment rates from a free FX provider.
 *
 * Rates are pulled against the base (AED) and written as `rate_to_base` — units
 * of the target per 1 AED, exactly what the provider's `rates` map already gives.
 * The base row and any `manual_override` row are left untouched, so a rate a staff
 * member has pinned is never clobbered. Failure is non-destructive: a provider
 * outage logs and returns without wiping the rates already on the table.
 */
class ExchangeRateUpdater
{
    /**
     * @return int The number of currencies whose rate was refreshed.
     */
    public function update(): int
    {
        $base = Currency::base()->code;

        $rates = $this->fetchRates($base);

        if ($rates === null) {
            return 0;
        }

        $updated = 0;

        $targets = Currency::query()
            ->active()
            ->where('is_base', false)
            ->where('manual_override', false)
            ->get();

        foreach ($targets as $currency) {
            $rate = $rates[$currency->code] ?? null;

            if (! is_numeric($rate) || (float) $rate <= 0) {
                continue;
            }

            $currency->forceFill([
                'rate_to_base' => (float) $rate,
                'rate_updated_at' => now(),
            ])->save();

            $updated++;
        }

        return $updated;
    }

    /**
     * Fetch the provider's base-to-target rate map, or null on any failure.
     *
     * @return array<string, float|int|string>|null
     */
    private function fetchRates(string $base): ?array
    {
        $endpoint = (string) config('payment.fx_endpoint', 'https://open.er-api.com/v6/latest/');

        try {
            $response = Http::timeout(10)->get($endpoint.$base);

            if (! $response->successful()) {
                Log::warning('FX update failed: provider returned '.$response->status());

                return null;
            }

            $rates = $response->json('rates');

            if (! is_array($rates) || $rates === []) {
                Log::warning('FX update failed: provider returned no rates.');

                return null;
            }

            return $rates;
        } catch (Throwable $e) {
            Log::warning('FX update failed: '.$e->getMessage());

            return null;
        }
    }
}
