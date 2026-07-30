<?php

namespace Tests\Feature;

use App\Checkout\CheckoutService;
use App\Checkout\Exceptions\CheckoutException;
use App\Discounts\DiscountService;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(ShippingZoneSeeder::class);
    }

    #[Test]
    public function a_percent_coupon_comes_off_the_subtotal(): void
    {
        $this->makeCoupon('SAVE10', ['type' => 'percent', 'value' => 10]);

        $result = app(DiscountService::class)->resolve('SAVE10', 1000, null, null);

        $this->assertTrue($result->valid);
        $this->assertSame(100.0, $result->amount);
    }

    #[Test]
    public function a_fixed_coupon_in_the_base_currency_is_used_as_is(): void
    {
        $this->makeCoupon('AED50', ['type' => 'fixed', 'value' => 50, 'currency' => null]);

        $result = app(DiscountService::class)->resolve('AED50', 1000, null, null);

        $this->assertSame(50.0, $result->amount);
    }

    #[Test]
    public function a_fixed_coupon_in_a_foreign_currency_is_converted_to_the_aed_base(): void
    {
        // 1 AED = 33 BDT, so a 66 BDT fixed discount is 2 AED in the base.
        $this->makeCoupon('BDT66', ['type' => 'fixed', 'value' => 66, 'currency' => 'BDT']);

        $result = app(DiscountService::class)->resolve('BDT66', 1000, null, null);

        $this->assertSame(2.0, $result->amount);
    }

    #[Test]
    public function a_coupon_below_its_minimum_subtotal_is_rejected(): void
    {
        $this->makeCoupon('MIN500', ['type' => 'percent', 'value' => 10, 'min_subtotal' => 500]);

        $this->assertFalse(app(DiscountService::class)->resolve('MIN500', 200, null, null)->valid);
        $this->assertTrue(app(DiscountService::class)->resolve('MIN500', 500, null, null)->valid);
    }

    #[Test]
    public function an_expired_or_not_yet_started_coupon_is_rejected(): void
    {
        $this->makeCoupon('EXPIRED', ['type' => 'percent', 'value' => 10, 'ends_at' => now()->subDay()]);
        $this->makeCoupon('FUTURE', ['type' => 'percent', 'value' => 10, 'starts_at' => now()->addDay()]);

        $this->assertFalse(app(DiscountService::class)->resolve('EXPIRED', 1000, null, null)->valid);
        $this->assertFalse(app(DiscountService::class)->resolve('FUTURE', 1000, null, null)->valid);
    }

    #[Test]
    public function an_inactive_coupon_is_rejected(): void
    {
        $this->makeCoupon('OFF', ['type' => 'percent', 'value' => 10, 'is_active' => false]);

        $this->assertFalse(app(DiscountService::class)->resolve('OFF', 1000, null, null)->valid);
    }

    #[Test]
    public function a_discount_is_clamped_to_the_subtotal(): void
    {
        $this->makeCoupon('HUGE', ['type' => 'fixed', 'value' => 5000, 'currency' => null]);

        $result = app(DiscountService::class)->resolve('HUGE', 1000, null, null);

        $this->assertSame(1000.0, $result->amount);
    }

    #[Test]
    public function placing_an_order_with_a_coupon_records_the_discount_total_and_cod_amount(): void
    {
        $this->makeProduct('gown', price: 1000, stock: 5);
        $this->makeCoupon('SAVE10', ['type' => 'percent', 'value' => 10]);

        $order = app(CheckoutService::class)->place(
            [['slug' => 'gown', 'size' => null, 'quantity' => 1]],
            $this->customer(),
            PaymentMethod::CashOnDelivery,
            null,
            'SAVE10',
        );

        // Subtotal 1000, BD free-shipping over 455 so shipping 0, 10% off = 100.
        $this->assertEquals(1000, $order->subtotal);
        $this->assertEquals(0, $order->shipping_total);
        $this->assertEquals(100, $order->discount_total);
        $this->assertEquals(900, $order->total);
        $this->assertEquals(900, $order->cod_amount);
        $this->assertEquals('SAVE10', $order->coupon_code);
    }

    #[Test]
    public function redeeming_a_coupon_writes_a_redemption_and_increments_times_used(): void
    {
        $this->makeProduct('gown', price: 1000, stock: 5);
        $coupon = $this->makeCoupon('SAVE10', ['type' => 'percent', 'value' => 10]);

        $order = app(CheckoutService::class)->place(
            [['slug' => 'gown', 'size' => null, 'quantity' => 1]],
            $this->customer(),
            PaymentMethod::CashOnDelivery,
            null,
            'SAVE10',
        );

        $this->assertSame(1, $coupon->fresh()->times_used);
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'amount' => 100,
        ]);
    }

    #[Test]
    public function a_global_usage_limit_stops_the_second_redemption(): void
    {
        $this->makeProduct('a', price: 1000, stock: 5);
        $this->makeProduct('b', price: 1000, stock: 5);
        $this->makeCoupon('ONCE', ['type' => 'percent', 'value' => 10, 'usage_limit' => 1]);

        $service = app(CheckoutService::class);
        $service->place([['slug' => 'a', 'size' => null, 'quantity' => 1]], $this->customer(), PaymentMethod::CashOnDelivery, null, 'ONCE');

        $this->expectException(CheckoutException::class);
        $service->place([['slug' => 'b', 'size' => null, 'quantity' => 1]], $this->customer(), PaymentMethod::CashOnDelivery, null, 'ONCE');
    }

    #[Test]
    public function the_second_use_is_blocked_and_the_order_rolls_back(): void
    {
        $this->makeProduct('a', price: 1000, stock: 5);
        $this->makeProduct('b', price: 1000, stock: 5);
        $this->makeCoupon('ONCE', ['type' => 'percent', 'value' => 10, 'usage_limit' => 1]);

        $service = app(CheckoutService::class);
        $service->place([['slug' => 'a', 'size' => null, 'quantity' => 1]], $this->customer(), PaymentMethod::CashOnDelivery, null, 'ONCE');

        try {
            $service->place([['slug' => 'b', 'size' => null, 'quantity' => 1]], $this->customer(), PaymentMethod::CashOnDelivery, null, 'ONCE');
        } catch (CheckoutException) {
            // expected
        }

        // Only the first order stands; the blocked one left no stock decrement.
        $this->assertSame(1, Order::count());
        $this->assertSame(5, Product::where('handle', 'b')->firstOrFail()->variants->first()->fresh()->inventory_quantity);
        $this->assertSame(1, Coupon::firstOrFail()->times_used);
    }

    #[Test]
    public function a_per_user_limit_blocks_the_same_user_twice(): void
    {
        $user = User::factory()->create();
        $this->makeProduct('a', price: 1000, stock: 5);
        $this->makeProduct('b', price: 1000, stock: 5);
        $this->makeCoupon('PERUSER', ['type' => 'percent', 'value' => 10, 'per_user_limit' => 1]);

        $service = app(CheckoutService::class);
        $service->place([['slug' => 'a', 'size' => null, 'quantity' => 1]], $this->customer(), PaymentMethod::CashOnDelivery, $user->id, 'PERUSER');

        $this->expectException(CheckoutException::class);
        $service->place([['slug' => 'b', 'size' => null, 'quantity' => 1]], $this->customer(), PaymentMethod::CashOnDelivery, $user->id, 'PERUSER');
    }

    #[Test]
    public function an_invalid_code_at_placement_rolls_the_order_back(): void
    {
        $product = $this->makeProduct('gown', price: 1000, stock: 5);

        try {
            app(CheckoutService::class)->place(
                [['slug' => 'gown', 'size' => null, 'quantity' => 1]],
                $this->customer(),
                PaymentMethod::CashOnDelivery,
                null,
                'DOESNOTEXIST',
            );
            $this->fail('Expected a CheckoutException.');
        } catch (CheckoutException) {
            // expected
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $product->variants->first()->fresh()->inventory_quantity);
    }

    #[Test]
    public function the_apply_endpoint_re_resolves_the_subtotal_and_ignores_a_client_sent_one(): void
    {
        $this->makeProduct('cheap', price: 200, stock: 5);
        $this->makeCoupon('MIN500', ['type' => 'percent', 'value' => 10, 'min_subtotal' => 500]);

        // The real bag is 200 AED, below the 500 minimum. A tampered client claims
        // a 9999 subtotal, but the server prices the bag itself and rejects it.
        $this->post('/checkout/coupon', [
            'code' => 'MIN500',
            'subtotal' => 9999,
            'items' => [['slug' => 'cheap', 'size' => null, 'quantity' => 1]],
        ])->assertSessionHasErrors('coupon');

        $this->assertNull(session('checkout.coupon'));
    }

    #[Test]
    public function the_apply_endpoint_stores_a_valid_code_in_the_session(): void
    {
        $this->makeProduct('gown', price: 1000, stock: 5);
        $this->makeCoupon('SAVE10', ['type' => 'percent', 'value' => 10]);

        $this->post('/checkout/coupon', [
            'code' => 'SAVE10',
            'items' => [['slug' => 'gown', 'size' => null, 'quantity' => 1]],
        ]);

        $this->assertSame('SAVE10', session('checkout.coupon'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCoupon(string $code, array $attributes): Coupon
    {
        return Coupon::create(array_merge([
            'code' => $code,
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ], $attributes, ['code' => $code]));
    }

    /**
     * @return array<string, mixed>
     */
    private function customer(): array
    {
        return [
            'name' => 'Rahim Uddin',
            'phone' => '01712345678',
            'email' => 'rahim@example.com',
            'address' => 'House 1, Road 2, Gulshan',
            'country' => 'BD',
            'district' => 'Dhaka',
            'note' => '',
        ];
    }

    private function makeProduct(string $handle, int $price, int $stock, ProductStatus $status = ProductStatus::Active): Product
    {
        $product = Product::create([
            'handle' => $handle,
            'title' => ucfirst(str_replace('-', ' ', $handle)),
            'status' => $status,
            'published_at' => $status === ProductStatus::Active ? now() : null,
        ]);

        $product->variants()->create([
            'option_key' => ProductVariant::makeOptionKey(null, 'Default Title', null, null),
            'option1' => 'Default Title',
            'price' => $price,
            'inventory_quantity' => $stock,
        ]);

        return $product->syncVariantAggregates()->load('variants');
    }
}
