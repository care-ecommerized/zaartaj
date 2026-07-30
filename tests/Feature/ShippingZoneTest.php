<?php

namespace Tests\Feature;

use App\Checkout\ShippingCalculator;
use App\Checkout\ZoneResolver;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingZoneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    private function zone(string $name, array $countries, int $priority, array $rates = [], bool $active = true): ShippingZone
    {
        $zone = ShippingZone::create([
            'name' => $name,
            'countries' => $countries,
            'priority' => $priority,
            'is_active' => $active,
        ]);

        foreach ($rates as $rate) {
            $zone->rates()->create($rate);
        }

        return $zone;
    }

    public function test_the_resolver_prefers_a_higher_priority_country_zone(): void
    {
        $this->zone('Low', ['AE'], 10);
        $high = $this->zone('High', ['AE', 'SA'], 90);
        $this->zone('World', [], 0);

        $this->assertSame($high->id, app(ZoneResolver::class)->forCountry('AE')?->id);
    }

    public function test_the_resolver_falls_back_to_the_catch_all_for_an_unknown_country(): void
    {
        $this->zone('GCC', ['AE'], 50);
        $world = $this->zone('World', [], 0);

        $this->assertSame($world->id, app(ZoneResolver::class)->forCountry('ZZ')?->id);
    }

    public function test_the_resolver_returns_null_when_nothing_matches_and_there_is_no_catch_all(): void
    {
        $this->zone('GCC', ['AE'], 50);

        $this->assertNull(app(ZoneResolver::class)->forCountry('ZZ'));
    }

    public function test_an_inactive_zone_is_ignored(): void
    {
        $this->zone('GCC', ['AE'], 50, [], active: false);
        $world = $this->zone('World', [], 0);

        $this->assertSame($world->id, app(ZoneResolver::class)->forCountry('AE')?->id);
    }

    public function test_a_flat_rate_is_charged(): void
    {
        $this->zone('GCC', ['AE'], 50, [
            ['method' => ShippingRate::METHOD_FLAT, 'amount' => 25],
        ]);

        $this->assertSame(25.0, (new ShippingCalculator)->charge(100, 'AE'));
    }

    public function test_an_order_value_bracket_is_selected(): void
    {
        $this->zone('GCC', ['AE'], 50, [
            ['method' => ShippingRate::METHOD_ORDER_VALUE, 'amount' => 30, 'min_threshold' => 0, 'max_threshold' => 200, 'priority' => 10],
            ['method' => ShippingRate::METHOD_ORDER_VALUE, 'amount' => 15, 'min_threshold' => 200, 'max_threshold' => null, 'priority' => 10],
        ]);

        $calc = new ShippingCalculator;

        $this->assertSame(30.0, $calc->charge(100, 'AE'));
        $this->assertSame(15.0, $calc->charge(500, 'AE'));
    }

    public function test_a_weight_bracket_is_selected(): void
    {
        $this->zone('GCC', ['AE'], 50, [
            ['method' => ShippingRate::METHOD_WEIGHT, 'amount' => 20, 'min_threshold' => 0, 'max_threshold' => 1, 'priority' => 10],
            ['method' => ShippingRate::METHOD_WEIGHT, 'amount' => 40, 'min_threshold' => 1, 'max_threshold' => 5, 'priority' => 10],
        ]);

        $calc = new ShippingCalculator;

        $this->assertSame(20.0, $calc->charge(100, 'AE', 0.5));
        $this->assertSame(40.0, $calc->charge(100, 'AE', 2.0));
    }

    public function test_a_weight_rate_is_skipped_when_the_weight_is_unknown(): void
    {
        $this->zone('GCC', ['AE'], 50, [
            ['method' => ShippingRate::METHOD_WEIGHT, 'amount' => 20, 'min_threshold' => 0, 'max_threshold' => 5, 'priority' => 90],
            ['method' => ShippingRate::METHOD_FLAT, 'amount' => 99, 'priority' => 10],
        ]);

        // No weight passed → the weight rate cannot match, so the flat rate wins.
        $this->assertSame(99.0, (new ShippingCalculator)->charge(100, 'AE'));
    }

    public function test_free_over_ships_free(): void
    {
        $this->zone('GCC', ['AE'], 50, [
            ['method' => ShippingRate::METHOD_FLAT, 'amount' => 25, 'free_over' => 500],
        ]);

        $calc = new ShippingCalculator;

        $this->assertSame(25.0, $calc->charge(499, 'AE'));
        $this->assertSame(0.0, $calc->charge(500, 'AE'));
    }

    public function test_the_calculator_falls_back_when_no_zone_resolves(): void
    {
        config()->set('checkout.shipping.fallback', 77);

        // No zones seeded at all → resolver returns null → configured fallback.
        $this->assertSame(77.0, (new ShippingCalculator)->charge(100, 'AE'));
    }

    public function test_bangladesh_parity_from_the_seeder(): void
    {
        $this->seed(ShippingZoneSeeder::class);

        $calc = new ShippingCalculator;

        // Below the BD free-over threshold, the seeded flat rate applies.
        $this->assertSame(5.0, $calc->charge(100, 'BD'));

        // At/above the threshold (≈ ৳15,000 → 455 AED), delivery is free — the
        // old free-over promise, preserved.
        $this->assertSame(0.0, $calc->charge(455, 'BD'));
        $this->assertSame(0.0, $calc->charge(90000, 'BD'));
    }
}
