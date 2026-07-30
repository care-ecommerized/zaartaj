<?php

namespace Tests\Feature;

use App\Delivery\ShipmentDispatcher;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('delivery.default', 'steadfast');
    }

    public function test_a_bangladesh_order_routes_to_steadfast(): void
    {
        $order = Order::factory()->create(['customer_country' => 'BD']);

        // draftFor never contacts the courier, so this asserts the routing choice
        // without any real HTTP.
        $shipment = app(ShipmentDispatcher::class)->draftFor($order);

        $this->assertSame('steadfast', $shipment->courier);
    }

    public function test_a_gcc_order_routes_to_aramex(): void
    {
        $order = Order::factory()->create(['customer_country' => 'AE']);

        $shipment = app(ShipmentDispatcher::class)->draftFor($order);

        $this->assertSame('aramex', $shipment->courier);
    }

    public function test_an_explicit_courier_always_wins(): void
    {
        $order = Order::factory()->create(['customer_country' => 'AE']);

        $shipment = app(ShipmentDispatcher::class)->draftFor($order, 'steadfast');

        $this->assertSame('steadfast', $shipment->courier);
    }
}
