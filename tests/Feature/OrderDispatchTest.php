<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\DispatchShipment;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('delivery.default', 'steadfast');
        config()->set('delivery.auto_dispatch', true);
    }

    public function test_confirming_a_pending_order_drafts_a_shipment_and_queues_its_booking(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $order->confirm();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame(1, Shipment::where('order_id', $order->id)->count());
        Queue::assertPushed(DispatchShipment::class, 1);
    }

    public function test_confirming_twice_queues_the_booking_only_once(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $order->confirm();
        $order->confirm();

        $this->assertSame(1, Shipment::where('order_id', $order->id)->count());
        Queue::assertPushed(DispatchShipment::class, 1);
    }

    public function test_marking_an_order_paid_confirms_and_queues_dispatch(): void
    {
        Queue::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'payment_status' => Order::PAYMENT_UNPAID,
        ]);

        $order->markPaid();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        Queue::assertPushed(DispatchShipment::class, 1);
    }

    public function test_auto_dispatch_off_confirms_without_queueing(): void
    {
        Queue::fake();
        config()->set('delivery.auto_dispatch', false);

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $order->confirm();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame(0, Shipment::count());
        Queue::assertNothingPushed();
    }

    public function test_admin_can_confirm_an_order_and_trigger_dispatch(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->actingAs($admin)
            ->post(route('admin.orders.confirm', $order))
            ->assertRedirect();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        Queue::assertPushed(DispatchShipment::class, 1);
    }

    public function test_admin_can_manually_queue_a_booking(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);

        $this->actingAs($admin)
            ->post(route('admin.orders.dispatch', $order))
            ->assertRedirect();

        $this->assertSame(1, Shipment::where('order_id', $order->id)->count());
        Queue::assertPushed(DispatchShipment::class, 1);
    }

    public function test_a_non_admin_cannot_confirm_orders(): void
    {
        Queue::fake();

        $user = User::factory()->create(['is_admin' => false]);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->actingAs($user)
            ->post(route('admin.orders.confirm', $order))
            ->assertNotFound();

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        Queue::assertNothingPushed();
    }
}
