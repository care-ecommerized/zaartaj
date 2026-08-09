<?php

namespace Database\Seeders;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Seed the worldwide shipping zones and their rates.
 *
 * All amounts are stored in the AED base currency. Bangladesh is quoted in taka
 * (inside Dhaka ৳80, outside ৳180) and converted here at the seeded BDT rate
 * (1 AED = 33 BDT), so a BD customer paying in taka is charged ≈ those figures.
 * Free delivery over ৳15,000 (≈ 455 AED) is preserved for Bangladesh.
 *
 * Bangladesh is split into two district-scoped zones (inside vs outside Dhaka)
 * because a country-wide zone cannot price by district. Staff can edit every
 * rate from the admin Shipping section. Idempotent: zones key on name and their
 * rates are rewritten on each run so these figures stay authoritative until
 * overridden in admin.
 */
class ShippingZoneSeeder extends Seeder
{
    /** Taka per 1 AED, matching the seeded BDT currency rate. */
    private const BDT_PER_AED = 33;

    public function run(): void
    {
        $bdt = fn (float $taka): float => round($taka / self::BDT_PER_AED, 2);

        $zones = [
            [
                'name' => 'UAE',
                'countries' => ['AE'],
                'districts' => null,
                'priority' => 100,
                'rates' => [
                    ['method' => ShippingRate::METHOD_FLAT, 'amount' => 25],
                ],
            ],
            [
                'name' => 'GCC',
                'countries' => ['SA', 'QA', 'KW', 'OM', 'BH'],
                'districts' => null,
                'priority' => 50,
                'rates' => [
                    ['method' => ShippingRate::METHOD_FLAT, 'amount' => 120],
                ],
            ],
            [
                // Inside Dhaka: ৳80. Higher priority than the outside-Dhaka zone so
                // a Dhaka address resolves here first.
                'name' => 'Bangladesh — Inside Dhaka',
                'countries' => ['BD'],
                'districts' => ['Dhaka'],
                'priority' => 120,
                'rates' => [
                    ['method' => ShippingRate::METHOD_FLAT, 'amount' => $bdt(80), 'free_over' => $bdt(15000)],
                ],
            ],
            [
                // Everywhere else in Bangladesh: ৳180. No district scope, so it is
                // the country-wide BD fallback for any non-Dhaka district.
                'name' => 'Bangladesh — Outside Dhaka',
                'countries' => ['BD'],
                'districts' => null,
                'priority' => 110,
                'rates' => [
                    ['method' => ShippingRate::METHOD_FLAT, 'amount' => $bdt(180), 'free_over' => $bdt(15000)],
                ],
            ],
            [
                // Catch-all: an empty country list prices any destination no other
                // zone claims (International). Kept lowest priority so it never
                // shadows a real zone.
                'name' => 'Rest of World',
                'countries' => [],
                'districts' => null,
                'priority' => 0,
                'rates' => [
                    ['method' => ShippingRate::METHOD_FLAT, 'amount' => 250],
                ],
            ],
        ];

        foreach ($zones as $definition) {
            $zone = ShippingZone::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'countries' => $definition['countries'],
                    'districts' => $definition['districts'],
                    'priority' => $definition['priority'],
                    'is_active' => true,
                ],
            );

            // Rewrite the zone's rates so the seed stays authoritative on re-run.
            $zone->rates()->delete();

            foreach ($definition['rates'] as $rate) {
                $zone->rates()->create([
                    'method' => $rate['method'],
                    'amount' => $rate['amount'],
                    'min_threshold' => $rate['min_threshold'] ?? null,
                    'max_threshold' => $rate['max_threshold'] ?? null,
                    'free_over' => $rate['free_over'] ?? null,
                    'priority' => $rate['priority'] ?? 0,
                ]);
            }
        }

        // Remove the pre-split Bangladesh zone from earlier seeds so it does not
        // shadow the new inside/outside-Dhaka zones.
        ShippingZone::where('name', 'Bangladesh')->delete();
    }
}
