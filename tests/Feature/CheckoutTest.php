<?php

namespace Tests\Feature;

use App\Checkout\CheckoutService;
use App\Checkout\Exceptions\CheckoutException;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The online-payment path converts the order total out of the AED base
        // into the gateway currency, which reads the currencies table.
        $this->seed(CurrencySeeder::class);

        // Shipping is now priced by admin-defined zones; the seeded BD zone
        // preserves the old free-over behaviour. Amounts are in AED.
        $this->seed(ShippingZoneSeeder::class);
    }

    #[Test]
    public function a_cash_on_delivery_order_is_placed_and_stock_is_decremented(): void
    {
        $product = $this->makeProduct('cinderella-gown', price: 45000, stock: 20);

        $response = $this->post('/checkout', $this->payload('cinderella-gown', quantity: 2, district: 'Dhaka'));

        $order = Order::firstOrFail();

        $response->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame(PaymentMethod::CashOnDelivery, $order->payment_method);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(Order::PAYMENT_UNPAID, $order->payment_status);

        // Subtotal 90000 is over the 15000 free-shipping threshold, so delivery is free.
        $this->assertEquals(90000, $order->subtotal);
        $this->assertEquals(0, $order->shipping_total);
        $this->assertEquals(90000, $order->total);
        // Cash on delivery collects the whole total on delivery.
        $this->assertEquals(90000, $order->cod_amount);

        $this->assertCount(1, $order->items);
        $this->assertEquals(45000, $order->items->first()->unit_price);
        $this->assertEquals(90000, $order->items->first()->line_total);

        // Stock fell from 20 to 18.
        $this->assertSame(18, $product->variants->first()->fresh()->inventory_quantity);
        $this->assertSame(18, $product->fresh()->total_inventory);
    }

    #[Test]
    public function bangladesh_delivery_is_charged_by_district_below_the_free_threshold(): void
    {
        // A small Bangladesh order sits below the free-over threshold (455 AED)
        // and is charged by district: inside Dhaka ৳70 (2.12 AED) and outside
        // Dhaka ৳150 (4.55 AED), both stored in the AED base.
        $this->makeProduct('small-clutch', price: 200, stock: 10);

        $this->post('/checkout', $this->payload('small-clutch', quantity: 1, district: 'Dhaka'));
        $this->assertEquals(2.12, Order::latest('id')->first()->shipping_total);

        Order::query()->delete();

        $this->makeProduct('small-bag', price: 200, stock: 10);
        $this->post('/checkout', $this->payload('small-bag', quantity: 1, district: 'Sylhet'));
        $this->assertEquals(4.55, Order::latest('id')->first()->shipping_total);
    }

    #[Test]
    public function delivery_is_priced_by_zone_for_international_destinations(): void
    {
        // UAE 25, GCC 200, and everywhere else (catch-all) 500 AED.
        $this->makeProduct('silk-scarf', price: 200, stock: 30);

        $this->post('/checkout', $this->intlPayload('silk-scarf', 'AE'));
        $this->assertEquals(25, Order::latest('id')->first()->shipping_total);

        Order::query()->delete();
        $this->post('/checkout', $this->intlPayload('silk-scarf', 'SA'));
        $this->assertEquals(200, Order::latest('id')->first()->shipping_total);

        Order::query()->delete();
        $this->post('/checkout', $this->intlPayload('silk-scarf', 'US'));
        $this->assertEquals(500, Order::latest('id')->first()->shipping_total);
    }

    #[Test]
    public function the_price_is_taken_from_the_database_not_the_request(): void
    {
        $this->makeProduct('cinderella-gown', price: 45000, stock: 5);

        // A tampered client sends a price of 1; the server ignores it entirely.
        $payload = $this->payload('cinderella-gown', quantity: 1, district: 'Dhaka');
        $payload['items'][0]['price'] = 1;
        $payload['items'][0]['unit_price'] = 1;

        $this->post('/checkout', $payload);

        $this->assertEquals(45000, Order::firstOrFail()->total);
    }

    #[Test]
    public function an_order_beyond_available_stock_is_rejected_and_nothing_is_placed(): void
    {
        $product = $this->makeProduct('cinderella-gown', price: 45000, stock: 3);

        $this->from('/checkout')
            ->post('/checkout', $this->payload('cinderella-gown', quantity: 5, district: 'Dhaka'))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('items.0');

        $this->assertSame(0, Order::count());
        // Stock is untouched — the rejection happened before any decrement committed.
        $this->assertSame(3, $product->variants->first()->fresh()->inventory_quantity);
    }

    #[Test]
    public function two_shoppers_cannot_both_buy_the_last_item(): void
    {
        $product = $this->makeProduct('last-gown', price: 10000, stock: 1);
        $service = app(CheckoutService::class);

        $lines = [['slug' => 'last-gown', 'size' => null, 'quantity' => 1]];
        $customer = ['name' => 'A', 'phone' => '01712345678', 'address' => 'x', 'district' => 'Dhaka'];

        $service->place($lines, $customer, PaymentMethod::CashOnDelivery);

        // The second attempt finds the shelf empty.
        $this->expectException(CheckoutException::class);
        $service->place($lines, ['name' => 'B'] + $customer, PaymentMethod::CashOnDelivery);

        $this->assertSame(0, $product->variants->first()->fresh()->inventory_quantity);
        $this->assertSame(1, Order::count());
    }

    #[Test]
    public function an_unpublished_product_cannot_be_bought(): void
    {
        $this->makeProduct('draft-gown', price: 5000, stock: 5, status: ProductStatus::Draft);

        $this->post('/checkout', $this->payload('draft-gown', quantity: 1, district: 'Dhaka'))
            ->assertSessionHasErrors('items.0');

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function checkout_validates_the_phone_number(): void
    {
        $this->makeProduct('cinderella-gown', price: 45000, stock: 5);

        $payload = $this->payload('cinderella-gown', quantity: 1, district: 'Dhaka');
        $payload['customer']['phone'] = '12345';

        $this->post('/checkout', $payload)->assertSessionHasErrors('customer.phone');
        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function a_guest_can_only_see_the_confirmation_for_the_order_they_just_placed(): void
    {
        $this->makeProduct('cinderella-gown', price: 45000, stock: 5);

        $this->post('/checkout', $this->payload('cinderella-gown', quantity: 1, district: 'Dhaka'));
        $order = Order::firstOrFail();

        // Same session that placed it — allowed.
        $this->get(route('checkout.confirmation', $order))->assertOk();

        // A fresh session with no claim to the order — forbidden.
        $this->flushSession();
        $this->get(route('checkout.confirmation', $order))->assertForbidden();
    }

    #[Test]
    public function an_online_payment_method_creates_a_pending_payment_against_the_order(): void
    {
        config(['payment.gateways.bkash.app_key' => 'test']);
        $this->makeProduct('cinderella-gown', price: 45000, stock: 5);

        // bKash will try to reach its API and fail in tests; the order must still
        // stand with the customer sent to its confirmation to retry.
        $payload = $this->payload('cinderella-gown', quantity: 1, district: 'Dhaka');
        $payload['payment_method'] = 'bkash';

        $this->post('/checkout', $payload);

        $order = Order::firstOrFail();
        $this->assertSame(PaymentMethod::Bkash, $order->payment_method);
        $this->assertEquals(0, $order->cod_amount);
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame($order->id, $order->payments()->first()->order_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $slug, int $quantity, string $district): array
    {
        return [
            'customer' => [
                'name' => 'Rahim Uddin',
                'phone' => '01712345678',
                'email' => 'rahim@example.com',
                'address' => 'House 1, Road 2, Gulshan',
                'country' => 'BD',
                'district' => $district,
                'note' => '',
            ],
            'payment_method' => 'cod',
            'items' => [
                ['slug' => $slug, 'size' => null, 'quantity' => $quantity],
            ],
        ];
    }

    /**
     * A checkout payload for an international (non-BD) destination: an E.164-ish
     * phone and a postcode (required abroad), and no district.
     *
     * @return array<string, mixed>
     */
    private function intlPayload(string $slug, string $country): array
    {
        return [
            'customer' => [
                'name' => 'Aisha Rahman',
                'phone' => '971501234567',
                'email' => 'aisha@example.com',
                'address' => 'Villa 12, Jumeirah 1',
                'country' => $country,
                'postcode' => '00000',
                'note' => '',
            ],
            'payment_method' => 'cod',
            'items' => [
                ['slug' => $slug, 'size' => null, 'quantity' => 1],
            ],
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
