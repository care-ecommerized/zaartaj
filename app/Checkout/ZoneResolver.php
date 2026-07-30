<?php

namespace App\Checkout;

use App\Models\ShippingZone;
use Illuminate\Support\Collection;

/**
 * Maps a destination country to the shipping zone that prices it.
 *
 * A country-specific zone always beats the catch-all, and the highest-priority
 * active zone wins when several claim the same country. When nothing at all
 * matches — no country zone and no catch-all — the resolver returns null and the
 * caller falls back to a configured default, so no destination is un-shippable.
 */
class ZoneResolver
{
    public function forCountry(string $iso2): ?ShippingZone
    {
        $iso2 = strtoupper(trim($iso2));

        $zones = $this->activeZones();

        // A zone that names the country, highest priority first.
        $match = $zones
            ->filter(fn (ShippingZone $zone) => $zone->covers($iso2))
            ->sortByDesc('priority')
            ->first();

        if ($match) {
            return $match;
        }

        // Otherwise the catch-all: an active zone that lists no countries.
        return $zones
            ->filter(fn (ShippingZone $zone) => $zone->isCatchAll())
            ->sortByDesc('priority')
            ->first();
    }

    /**
     * @return Collection<int, ShippingZone>
     */
    private function activeZones(): Collection
    {
        return ShippingZone::query()->active()->with('rates')->get();
    }
}
