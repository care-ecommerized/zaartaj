<?php

namespace Tests\Feature;

use App\Delivery\DeliveryManager;
use App\Delivery\Enums\ShipmentStatus;
use App\Delivery\Exceptions\DeliveryException;
use App\Delivery\ShipmentDispatcher;
use App\Delivery\ShipmentRequest;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteadfastDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('delivery.default', 'steadfast');
        config()->set('delivery.couriers.steadfast', [
            'driver' => 'steadfast',
            'base_url' => 'https://steadfast.test/api/v1',
            'api_key' => 'key',
            'secret_key' => 'secret',
            'webhook_token' => 'hook-token',
            'timeout' => 5,
            'retries' => 1,
            'default_delivery_type' => 0,
            'bulk_limit' => 500,
        ]);
    }

    public function test_dispatching_an_order_books_a_consignment_and_marks_the_order_shipped(): void
    {
        Http::fake([
            'steadfast.test/*/create_order' => Http::response([
                'status' => 200,
                'message' => 'Consignment has been created successfully.',
                'consignment' => [
                    'consignment_id' => 1424107,
                    'invoice' => 'ZT-0001',
                    'tracking_code' => '15BAEB8A',
                    'status' => 'in_review',
                ],
            ]),
        ]);

        $order = Order::factory()->create([
            'order_number' => 'ZT-0001',
            'customer_phone' => '+8801712345678',
            'cod_amount' => 1500,
        ]);

        $shipment = app(ShipmentDispatcher::class)->dispatchFor($order);

        $this->assertSame('1424107', $shipment->consignment_id);
        $this->assertSame('15BAEB8A', $shipment->tracking_code);
        $this->assertSame(ShipmentStatus::InReview, $shipment->status);
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);

        // The country code must be stripped and COD sent as a number.
        Http::assertSent(fn ($request) => $request['recipient_phone'] === '01712345678'
            && $request['invoice'] === 'ZT-0001'
            && $request['cod_amount'] === 1500.0
            && $request->hasHeader('Api-Key', 'key')
            && $request->hasHeader('Secret-Key', 'secret'));
    }

    public function test_a_rejected_consignment_marks_the_shipment_failed_without_shipping_the_order(): void
    {
        Http::fake([
            'steadfast.test/*' => Http::response([
                'status' => 400,
                'message' => 'The given data was invalid.',
                'errors' => ['invoice' => ['The invoice has already been taken.']],
            ], 400),
        ]);

        $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);

        try {
            app(ShipmentDispatcher::class)->dispatchFor($order);
            $this->fail('Expected a DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertStringContainsString('The invoice has already been taken.', $e->getMessage());
        }

        $shipment = Shipment::sole();

        $this->assertSame(ShipmentStatus::Failed, $shipment->status);
        $this->assertNull($shipment->consignment_id);
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
    }

    public function test_dispatching_twice_does_not_book_a_second_parcel(): void
    {
        Http::fake([
            'steadfast.test/*/create_order' => Http::response([
                'status' => 200,
                'consignment' => ['consignment_id' => 999, 'tracking_code' => 'AA11', 'status' => 'in_review'],
            ]),
        ]);

        $order = Order::factory()->create();
        $dispatcher = app(ShipmentDispatcher::class);

        $dispatcher->dispatchFor($order);
        $dispatcher->dispatchFor($order);

        $this->assertSame(1, Shipment::count());
        Http::assertSentCount(1);
    }

    public function test_the_webhook_settles_the_shipment_and_the_order(): void
    {
        $shipment = Shipment::factory()->dispatched()->create(['consignment_id' => '1424107']);

        $this->withToken('hook-token')
            ->postJson(route('webhooks.steadfast'), [
                'consignment_id' => 1424107,
                'invoice' => $shipment->invoice,
                'status' => 'delivered',
                'cod_amount' => 1500,
            ])
            ->assertOk();

        $shipment->refresh();

        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertSame('delivered', $shipment->provider_status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame(OrderStatus::Delivered, $shipment->order->status);
    }

    public function test_the_webhook_rejects_a_bad_token(): void
    {
        $shipment = Shipment::factory()->dispatched()->create(['consignment_id' => '1424107']);

        $this->withToken('wrong')
            ->postJson(route('webhooks.steadfast'), [
                'consignment_id' => 1424107,
                'status' => 'delivered',
            ])
            ->assertUnauthorized();

        $this->assertNotSame(ShipmentStatus::Delivered, $shipment->refresh()->status);
    }

    public function test_a_late_webhook_cannot_move_a_settled_shipment_backwards(): void
    {
        $shipment = Shipment::factory()->dispatched()->create([
            'consignment_id' => '1424107',
            'status' => ShipmentStatus::Delivered,
            'provider_status' => 'delivered',
        ]);

        $this->withToken('hook-token')
            ->postJson(route('webhooks.steadfast'), [
                'consignment_id' => 1424107,
                'status' => 'pending',
            ])
            ->assertOk();

        $this->assertSame(ShipmentStatus::Delivered, $shipment->refresh()->status);
    }

    public function test_reconciling_pulls_the_authoritative_status(): void
    {
        Http::fake([
            'steadfast.test/*/status_by_cid/*' => Http::response([
                'status' => 200,
                'delivery_status' => 'partial_delivered',
            ]),
        ]);

        $shipment = Shipment::factory()->dispatched()->create(['consignment_id' => '1424107']);

        app(ShipmentDispatcher::class)->sync($shipment);

        $this->assertSame(ShipmentStatus::PartiallyDelivered, $shipment->refresh()->status);
        $this->assertSame(OrderStatus::Delivered, $shipment->order->status);
    }

    public function test_approval_pending_statuses_are_not_treated_as_settled(): void
    {
        Http::fake([
            'steadfast.test/*/status_by_cid/*' => Http::response([
                'status' => 200,
                'delivery_status' => 'delivered_approval_pending',
            ]),
        ]);

        $shipment = Shipment::factory()->dispatched()->create(['consignment_id' => '1424107']);

        app(ShipmentDispatcher::class)->sync($shipment);
        $shipment->refresh();

        $this->assertSame(ShipmentStatus::AwaitingApproval, $shipment->status);
        $this->assertFalse($shipment->status->isFinal());
        $this->assertNull($shipment->delivered_at);
        $this->assertSame(OrderStatus::Confirmed, $shipment->order->status);
    }

    public function test_bulk_creation_reports_per_parcel_failures(): void
    {
        Http::fake([
            'steadfast.test/*/bulk-order' => Http::response([
                'status' => 200,
                'data' => [
                    ['invoice' => 'A-1', 'status' => 'success', 'consignment_id' => 11, 'tracking_code' => 'T1'],
                    ['invoice' => 'A-2', 'status' => 'error', 'message' => 'Recipient phone is invalid.'],
                ],
            ]),
        ]);

        $results = app(DeliveryManager::class)->driver()->createShipments([
            new ShipmentRequest('A-1', 'One', '01712345678', 'Dhaka', 100.0),
            new ShipmentRequest('A-2', 'Two', '01812345678', 'Dhaka', 200.0),
        ]);

        $this->assertTrue($results[0]->successful);
        $this->assertSame('11', $results[0]->consignmentId);

        $this->assertFalse($results[1]->successful);
        $this->assertSame('Recipient phone is invalid.', $results[1]->message);
    }

    public function test_invalid_phone_numbers_are_rejected_before_the_api_is_called(): void
    {
        Http::fake();

        $this->expectException(DeliveryException::class);

        ShipmentRequest::normalisePhone('0171234');
    }
}
