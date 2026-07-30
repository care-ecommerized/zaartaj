<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCouponTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_an_admin_can_list_coupons(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.coupons.index'))
            ->assertOk();
    }

    public function test_a_non_admin_gets_a_404(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.coupons.index'))
            ->assertNotFound();
    }

    public function test_an_admin_can_create_a_coupon(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.coupons.store'), [
                'code' => 'save10',
                'type' => 'percent',
                'value' => 10,
                'min_subtotal' => 500,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.coupons.index'));

        // The code is normalised to uppercase.
        $coupon = Coupon::firstOrFail();
        $this->assertSame('SAVE10', $coupon->code);
        $this->assertSame('percent', $coupon->type);
        $this->assertEquals(10, $coupon->value);
    }

    public function test_a_percent_value_over_100_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.coupons.store'), [
                'code' => 'BAD',
                'type' => 'percent',
                'value' => 150,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('value');

        $this->assertSame(0, Coupon::count());
    }

    public function test_a_duplicate_code_is_rejected(): void
    {
        Coupon::create(['code' => 'DUP', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('admin.coupons.store'), [
                'code' => 'dup',
                'type' => 'percent',
                'value' => 5,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Coupon::count());
    }

    public function test_an_admin_can_update_a_coupon(): void
    {
        $coupon = Coupon::create(['code' => 'OLD', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->put(route('admin.coupons.update', $coupon), [
                'code' => 'NEW',
                'type' => 'fixed',
                'value' => 25,
                'currency' => 'AED',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.coupons.index'));

        $coupon->refresh();
        $this->assertSame('NEW', $coupon->code);
        $this->assertSame('fixed', $coupon->type);
        $this->assertFalse($coupon->is_active);
    }

    public function test_an_admin_can_delete_a_coupon(): void
    {
        $coupon = Coupon::create(['code' => 'TEMP', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect(route('admin.coupons.index'));

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_a_non_admin_cannot_create_a_coupon(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('admin.coupons.store'), [
                'code' => 'SNEAKY',
                'type' => 'percent',
                'value' => 10,
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertSame(0, Coupon::count());
    }
}
