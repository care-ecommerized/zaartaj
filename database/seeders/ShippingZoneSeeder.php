<?php

namespace Database\Seeders;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Seed the worldwide shipping zones and their rates.
 *
 * All amounts are in the AED base currency. The figures below are sensible
 * launch defaults — TUNE THEM IN ADMIN once the catalogue re-base settles the
 * real AED price points. Idempotent: zones key on name and their rates are
 * rewritten on every run so edits here stay authoritative until staff override.
 *
 * Bangladesh note: the pre-Phase-C store priced BD as a flat ~60/120 BDT with
 * free delivery over ৳15,000. Re-expressed in AED that is a small flat charge
 * with free delivery over ~455 AED (15,000 / 33). The district split (inside vs
 * outside Dhaka) is intentionally dropped — zones price by country, and staff
 * can add finer BD rates in admin if needed.
 */
class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Bangladesh',
                'countries' => ['BD'],
                'priority' => 100,
                'rates' => [
                    // Flat ~5 AED (≈ ৳165) with free delivery over ~455 AED
                    // (≈ ৳15,000). Tune the exact BD figures in admin.
                    ['method' => ShippingRate::METHOD_FLAT, 'amount' => 5, 'free_over' => 455],
                ],
            ],
            [
                'name' => 'GCC',
                'countries' => ['AE', 'SA', 'QA', 'KW', 'OM', 'BH'],
                'priority' => 50,
                'rates' => [
                    ['method' => ShippingRate::METHOD_FLAT, 'amount' => 25, 'free_over' => 500],
                ],
            ],
            [
                // Catch-all: an empty country list prices any destination no other
                // zone claims. Kept lowest priority so it never shadows a real zone.
                'name' => 'Rest of World',
                'countries' => [],
                'priority' => 0,
                'rates' => [
                    ['method' => ShippingRate::METHOD_FLAT, 'amount' => 80, 'free_over' => null],
                ],
            ],
        ];

        foreach ($zones as $definition) {
            $zone = ShippingZone::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'countries' => $definition['countries'],
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
    }
}
