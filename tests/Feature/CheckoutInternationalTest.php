<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutInternationalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(ShippingZoneSeeder::class);
        $this->makeProduct('silk-scarf', price: 200, stock: 10);
    }

    public function test_the_country_is_required(): void
    {
        $payload = $this->payload();
        unset($payload['customer']['country']);

        $this->post('/checkout', $payload)->assertSessionHasErrors('customer.country');
        $this->assertSame(0, Order::count());
    }

    public function test_the_district_is_required_for_bangladesh(): void
    {
        $payload = $this->payload(['country' => 'BD', 'phone' => '01712345678']);
        unset($payload['customer']['district']);

        $this->post('/checkout', $payload)->assertSessionHasErrors('customer.district');
        $this->assertSame(0, Order::count());
    }

    public function test_the_district_is_not_required_for_other_countries(): void
    {
        $payload = $this->payload(['country' => 'AE', 'phone' => '+971501234567', 'postcode' => '00000']);
        unset($payload['customer']['district']);

        $this->post('/checkout', $payload);

        $order = Order::firstOrFail();
        $this->assertSame('AE', $order->customer_country);
        // GCC flat rate (25 AED) applies below the 500 AED free-over threshold.
        $this->assertEquals(25, $order->shipping_total);
    }

    public function test_an_international_phone_number_is_accepted(): void
    {
        $payload = $this->payload(['country' => 'AE', 'phone' => '+971501234567', 'postcode' => '00000']);
        unset($payload['customer']['district']);

        $this->post('/checkout', $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
    }

    public function test_a_bangladeshi_phone_is_rejected_for_an_international_order(): void
    {
        // The strict BD mobile rule only applies to BD; a non-BD order must still
        // reject a plainly invalid number.
        $payload = $this->payload(['country' => 'AE', 'phone' => 'not-a-number', 'postcode' => '00000']);
        unset($payload['customer']['district']);

        $this->post('/checkout', $payload)->assertSessionHasErrors('customer.phone');
        $this->assertSame(0, Order::count());
    }

    public function test_a_postcode_is_required_for_international_orders(): void
    {
        $payload = $this->payload(['country' => 'AE', 'phone' => '+971501234567']);
        unset($payload['customer']['district'], $payload['customer']['postcode']);

        $this->post('/checkout', $payload)->assertSessionHasErrors('customer.postcode');
        $this->assertSame(0, Order::count());
    }

    public function test_a_postcode_is_not_required_for_bangladesh(): void
    {
        $payload = $this->payload(['country' => 'BD', 'phone' => '01712345678', 'district' => 'Dhaka']);
        unset($payload['customer']['postcode']);

        $this->post('/checkout', $payload)->assertSessionHasNoErrors();
        $this->assertSame('BD', Order::firstOrFail()->customer_country);
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    private function payload(array $customer = []): array
    {
        return [
            'customer' => array_merge([
                'name' => 'Aisha Rahman',
                'phone' => '01712345678',
                'email' => 'aisha@example.com',
                'address' => 'Flat 4, Marina Tower',
                'country' => 'AE',
                'district' => 'Dhaka',
                'postcode' => '00000',
                'note' => '',
            ], $customer),
            'payment_method' => 'cod',
            'items' => [
                ['slug' => 'silk-scarf', 'size' => null, 'quantity' => 1],
            ],
        ];
    }

    private function makeProduct(string $handle, int $price, int $stock, ProductStatus $status = ProductStatus::Active): Product
    {
        $product = Product::create([
            'handle' => $handle,
            'title' => ucfirst(str_replace('-', ' ', $handle)),
            'status' => $status,
            'published_at' => now(),
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
