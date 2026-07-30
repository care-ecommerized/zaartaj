<?php

namespace Tests\Feature;

use App\Delivery\DeliveryManager;
use App\Delivery\Enums\ShipmentStatus;
use App\Delivery\Exceptions\DeliveryException;
use App\Delivery\ShipmentDispatcher;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AramexDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('delivery.default', 'aramex');
        config()->set('delivery.couriers.aramex', [
            'driver' => 'aramex',
            'base_url' => 'https://aramex.test',
            'client_info' => [
                'UserName' => 'api@zaartaj.test',
                'Password' => 'secret',
                'Version' => 'v1.0',
                'AccountNumber' => '60000',
                'AccountPin' => '123456',
                'AccountEntity' => 'DAC',
                'AccountCountryCode' => 'BD',
                'Source' => 24,
            ],
            'shipper' => [
                'name' => 'Zaartaj Warehouse',
                'company' => 'Zaartaj',
                'phone' => '028000000',
                'email' => 'ops@zaartaj.test',
                'line1' => '12 Gulshan Ave',
                'city' => 'Dhaka',
                'postcode' => '1212',
                'country_code' => 'BD',
            ],
            'origin_country' => 'BD',
            'default_product_type_dom' => 'OND',
            'default_product_type_exp' => 'PPX',
            'default_weight' => 0.5,
            'default_pieces' => 1,
            'default_description' => 'Merchandise',
            'default_customs_value' => 10,
            'timeout' => 5,
            'retries' => 1,
        ]);
    }

    private function fakeCreateOk(string $awb = '4700000001'): void
    {
        Http::fake([
            'aramex.test/*/CreateShipments' => Http::response([
                'HasErrors' => false,
                'Notifications' => [],
                'ProcessedShipments' => [[
                    'ID' => $awb,
                    'Reference1' => 'ZT-0001',
                    'ForeignHAWBNumber' => 'ZT-0001',
                    'HasErrors' => false,
                    'Notifications' => [],
                    'ShipmentLabel' => ['LabelURL' => 'https://aramex.test/labels/'.$awb.'.pdf'],
                ]],
            ]),
        ]);
    }

    public function test_domestic_cod_dispatch_books_an_awb_and_ships_the_order(): void
    {
        $this->fakeCreateOk('4700000001');

        $order = Order::factory()->create([
            'order_number' => 'ZT-0001',
            'customer_phone' => '+8801712345678',
            'customer_district' => 'Dhaka',
            'customer_country' => 'BD',
            'cod_amount' => 1500,
        ]);

        $shipment = app(ShipmentDispatcher::class)->dispatchFor($order, 'aramex');

        $this->assertSame('4700000001', $shipment->consignment_id);
        $this->assertSame('4700000001', $shipment->tracking_code);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);

        Http::assertSent(function ($request) {
            $s = $request['Shipments'][0];

            return $request['ClientInfo']['AccountNumber'] === '60000'
                && $request['ClientInfo']['UserName'] === 'api@zaartaj.test'
                && $s['Shipper']['PartyAddress']['City'] === 'Dhaka'
                && $s['Details']['ProductGroup'] === 'DOM'
                && $s['Details']['ProductType'] === 'OND'
                && $s['Details']['PaymentType'] === 'C'
                && $s['Details']['CashOnDeliveryAmount']['Value'] === 1500.0
                // The BD-only normalisation must NOT touch Aramex — raw passes through.
                && $s['Consignee']['Contact']['PhoneNumber1'] === '+8801712345678'
                && $s['Consignee']['PartyAddress']['CountryCode'] === 'BD';
        });
    }

    public function test_international_prepaid_dispatch_uses_exp_and_no_cod(): void
    {
        $this->fakeCreateOk('4700000002');

        $order = Order::factory()->create([
            'order_number' => 'ZT-0001',
            'customer_country' => 'AE',
            'cod_amount' => 0,
        ]);

        app(ShipmentDispatcher::class)->dispatchFor($order, 'aramex');

        Http::assertSent(function ($request) {
            $details = $request['Shipments'][0]['Details'];

            return $details['ProductGroup'] === 'EXP'
                && $details['ProductType'] === 'PPX'
                && $details['PaymentType'] === 'P'
                && ! array_key_exists('CashOnDeliveryAmount', $details)
                && $details['CustomsValueAmount']['Value'] === 10.0;
        });
    }

    public function test_a_shipment_error_marks_the_shipment_failed(): void
    {
        Http::fake([
            'aramex.test/*/CreateShipments' => Http::response([
                'HasErrors' => true,
                'Notifications' => [],
                'ProcessedShipments' => [[
                    'ID' => '',
                    'Reference1' => 'ZT-0001',
                    'HasErrors' => true,
                    'Notifications' => [
                        ['Code' => 'ERR52', 'Message' => 'Consignee phone number is required.'],
                    ],
                ]],
            ]),
        ]);

        $order = Order::factory()->create(['order_number' => 'ZT-0001', 'status' => OrderStatus::Confirmed]);

        app(ShipmentDispatcher::class)->dispatchFor($order, 'aramex');

        $shipment = Shipment::sole();

        $this->assertSame(ShipmentStatus::Failed, $shipment->status);
        $this->assertNull($shipment->consignment_id);
        $this->assertStringContainsString('Consignee phone number is required.', $shipment->failure_reason);
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
    }

    public function test_a_total_failure_with_no_processed_shipments_throws(): void
    {
        Http::fake([
            'aramex.test/*/CreateShipments' => Http::response([
                'HasErrors' => true,
                'Notifications' => [['Code' => 'ERR01', 'Message' => 'Invalid credentials.']],
                'ProcessedShipments' => [],
            ]),
        ]);

        $order = Order::factory()->create(['order_number' => 'ZT-0001']);

        try {
            app(ShipmentDispatcher::class)->dispatchFor($order, 'aramex');
            $this->fail('Expected a DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertStringContainsString('Invalid credentials.', $e->getMessage());
        }

        $this->assertSame(ShipmentStatus::Failed, Shipment::sole()->status);
    }

    public function test_tracking_a_delivered_shipment_settles_it(): void
    {
        Http::fake([
            'aramex.test/*/TrackShipments' => Http::response([
                'HasErrors' => false,
                'TrackingResults' => [[
                    'Key' => '4700000001',
                    'Value' => [
                        ['UpdateCode' => 'SH060', 'UpdateDescription' => 'Out for delivery'],
                        ['UpdateCode' => 'SH005', 'UpdateDescription' => 'Shipment delivered'],
                    ],
                ]],
            ]),
        ]);

        $shipment = Shipment::factory()->dispatched()->create([
            'courier' => 'aramex',
            'consignment_id' => '4700000001',
        ]);

        app(ShipmentDispatcher::class)->sync($shipment);

        $this->assertSame(ShipmentStatus::Delivered, $shipment->refresh()->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame(OrderStatus::Delivered, $shipment->order->status);
    }

    public function test_tracking_a_returned_shipment_marks_the_order_returned(): void
    {
        Http::fake([
            'aramex.test/*/TrackShipments' => Http::response([
                'HasErrors' => false,
                'TrackingResults' => [[
                    'Key' => '4700000001',
                    'Value' => [
                        ['UpdateCode' => 'SH999', 'UpdateDescription' => 'Returned to shipper'],
                    ],
                ]],
            ]),
        ]);

        $shipment = Shipment::factory()->dispatched()->create([
            'courier' => 'aramex',
            'consignment_id' => '4700000001',
        ]);

        app(ShipmentDispatcher::class)->sync($shipment);

        $this->assertSame(ShipmentStatus::Cancelled, $shipment->refresh()->status);
        $this->assertSame(OrderStatus::Returned, $shipment->order->status);
    }

    public function test_an_unmapped_tracking_code_stays_non_final(): void
    {
        Http::fake([
            'aramex.test/*/TrackShipments' => Http::response([
                'HasErrors' => false,
                'TrackingResults' => [[
                    'Key' => '4700000001',
                    'Value' => [
                        ['UpdateCode' => 'ZZ123', 'UpdateDescription' => 'Some internal milestone'],
                    ],
                ]],
            ]),
        ]);

        $shipment = Shipment::factory()->dispatched()->create([
            'courier' => 'aramex',
            'consignment_id' => '4700000001',
        ]);

        app(ShipmentDispatcher::class)->sync($shipment);
        $shipment->refresh();

        $this->assertSame(ShipmentStatus::Unknown, $shipment->status);
        $this->assertFalse($shipment->status->isFinal());
    }

    public function test_track_by_invoice_and_balance_are_unsupported(): void
    {
        $aramex = app(DeliveryManager::class)->driver('aramex');

        try {
            $aramex->trackByInvoice('ZT-0001');
            $this->fail('Expected trackByInvoice to throw.');
        } catch (DeliveryException $e) {
            $this->assertStringContainsString('does not support', $e->getMessage());
        }

        try {
            $aramex->balance();
            $this->fail('Expected balance to throw.');
        } catch (DeliveryException $e) {
            $this->assertStringContainsString('does not support', $e->getMessage());
        }
    }
}
