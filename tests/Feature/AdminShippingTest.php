<?php

namespace Tests\Feature;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShippingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_an_admin_can_list_zones(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.shipping.index'))
            ->assertOk();
    }

    public function test_a_non_admin_gets_a_404(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.shipping.index'))
            ->assertNotFound();
    }

    public function test_an_admin_can_create_a_zone_with_rates(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.shipping.store'), [
                'name' => 'GCC',
                'countries' => ['AE', 'SA'],
                'priority' => 50,
                'is_active' => true,
                'rates' => [
                    ['method' => 'flat', 'amount' => 25, 'free_over' => 500, 'priority' => 0],
                ],
            ])
            ->assertRedirect(route('admin.shipping.index'));

        $zone = ShippingZone::where('name', 'GCC')->firstOrFail();

        $this->assertSame(['AE', 'SA'], $zone->countries);
        $this->assertSame(50, $zone->priority);
        $this->assertSame(1, $zone->rates()->count());
        $this->assertEquals(25, $zone->rates()->first()->amount);
    }

    public function test_a_catch_all_zone_can_be_created_with_no_countries(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.shipping.store'), [
                'name' => 'Rest of World',
                'countries' => [],
                'priority' => 0,
                'is_active' => true,
                'rates' => [['method' => 'flat', 'amount' => 80]],
            ])
            ->assertRedirect();

        $this->assertTrue(ShippingZone::where('name', 'Rest of World')->firstOrFail()->isCatchAll());
    }

    public function test_an_admin_can_update_a_zone_and_its_rates_are_rewritten(): void
    {
        $zone = ShippingZone::create(['name' => 'Old', 'countries' => ['AE'], 'priority' => 10, 'is_active' => true]);
        $zone->rates()->create(['method' => ShippingRate::METHOD_FLAT, 'amount' => 5]);

        $this->actingAs($this->admin())
            ->put(route('admin.shipping.update', $zone), [
                'name' => 'New',
                'countries' => ['AE', 'QA'],
                'priority' => 60,
                'is_active' => false,
                'rates' => [
                    ['method' => 'order_value', 'amount' => 12, 'min_threshold' => 0, 'max_threshold' => 300],
                    ['method' => 'order_value', 'amount' => 6, 'min_threshold' => 300, 'max_threshold' => null],
                ],
            ])
            ->assertRedirect(route('admin.shipping.index'));

        $zone->refresh();

        $this->assertSame('New', $zone->name);
        $this->assertSame(['AE', 'QA'], $zone->countries);
        $this->assertFalse($zone->is_active);
        // Old single flat rate replaced by the two submitted brackets.
        $this->assertSame(2, $zone->rates()->count());
    }

    public function test_an_admin_can_delete_a_zone(): void
    {
        $zone = ShippingZone::create(['name' => 'Temp', 'countries' => ['AE'], 'priority' => 10, 'is_active' => true]);
        $zone->rates()->create(['method' => ShippingRate::METHOD_FLAT, 'amount' => 5]);

        $this->actingAs($this->admin())
            ->delete(route('admin.shipping.destroy', $zone))
            ->assertRedirect(route('admin.shipping.index'));

        $this->assertDatabaseMissing('shipping_zones', ['id' => $zone->id]);
        // The cascade removes the zone's rates too.
        $this->assertDatabaseMissing('shipping_rates', ['shipping_zone_id' => $zone->id]);
    }

    public function test_a_non_admin_cannot_create_a_zone(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('admin.shipping.store'), [
                'name' => 'Sneaky',
                'countries' => ['AE'],
                'priority' => 1,
                'is_active' => true,
                'rates' => [],
            ])
            ->assertNotFound();

        $this->assertSame(0, ShippingZone::count());
    }
}
