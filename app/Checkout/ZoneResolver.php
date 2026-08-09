<?php

namespace App\Checkout;

use App\Models\ShippingZone;
use Illuminate\Support\Collection;

/**
 * Maps a destination (country + optional district) to the shipping zone that
 * prices it.
 *
 * Resolution order, most specific first:
 *   1. A zone that covers the country AND names the district (e.g. inside Dhaka).
 *   2. A country-wide zone (covers the country, no district scope).
 *   3. The catch-all zone (no countries at all).
 *   4. null — the caller falls back to a configured default, so no destination
 *      is ever un-shippable.
 *
 * Within each tier the highest-priority active zone wins.
 */
class ZoneResolver
{
    public function forDestination(string $iso2, ?string $district = null): ?ShippingZone
    {
        $iso2 = strtoupper(trim($iso2));

        $zones = $this->activeZones();

        // 1. A district-scoped zone for this country that names the district.
        $districtMatch = $zones
            ->filter(fn (ShippingZone $zone) => $zone->covers($iso2) && $zone->coversDistrict($district))
            ->sortByDesc('priority')
            ->first();

        if ($districtMatch) {
            return $districtMatch;
        }

        // 2. A country-wide zone for this country (no district scope).
        $countryMatch = $zones
            ->filter(fn (ShippingZone $zone) => $zone->covers($iso2) && ! $zone->isDistrictScoped())
            ->sortByDesc('priority')
            ->first();

        if ($countryMatch) {
            return $countryMatch;
        }

        // 3. The catch-all: an active zone that lists no countries.
        return $zones
            ->filter(fn (ShippingZone $zone) => $zone->isCatchAll())
            ->sortByDesc('priority')
            ->first();
    }

    /**
     * Country-only resolution, kept for callers that have no district.
     */
    public function forCountry(string $iso2): ?ShippingZone
    {
        return $this->forDestination($iso2);
    }

    /**
     * @return Collection<int, ShippingZone>
     */
    private function activeZones(): Collection
    {
        return ShippingZone::query()->active()->with('rates')->get();
    }
}
