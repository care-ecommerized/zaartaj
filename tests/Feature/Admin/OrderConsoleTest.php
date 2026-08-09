<?php

namespace Tests\Feature\Admin;

use App\Checkout\CheckoutService;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The home country the region toggle treats as "local".
        config()->set('checkout.local_country', 'AE');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'placed_at' => now(),
            'payment_status' => Order::PAYMENT_UNPAID,
            'customer_country' => 'AE',
        ], $attributes));
    }

    #[Test]
    public function the_index_renders_orders_for_an_admin(): void
    {
        $this->order(['customer_name' => 'Layla Noor', 'status' => OrderStatus::Confirmed]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/index')
                ->has('orders.data', 1)
                ->where('orders.data.0.customer_name', 'Layla Noor')
                ->has('statusSummary')
                ->has('salesCards'));
    }

    #[Test]
    public function the_index_is_invisible_to_a_non_admin(): void
    {
        $this->order();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.orders.index'))
            ->assertNotFound();
    }

    #[Test]
    public function the_status_filter_narrows_the_list(): void
    {
        $this->order(['status' => OrderStatus::Confirmed]);
        $this->order(['status' => OrderStatus::Delivered]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['status' => 'delivered']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.status', 'delivered'));
    }

    #[Test]
    public function the_payment_and_search_filters_narrow_the_list(): void
    {
        $this->order(['payment_status' => Order::PAYMENT_PAID, 'customer_email' => 'paid@zaartaj.test']);
        $this->order(['payment_status' => Order::PAYMENT_UNPAID, 'customer_email' => 'unpaid@zaartaj.test']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['payment_status' => 'paid']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)
                ->where('orders.data.0.payment_status', 'paid'));

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['search' => 'unpaid@zaartaj']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)
                ->where('orders.data.0.customer_email', 'unpaid@zaartaj.test'));
    }

    #[Test]
    public function the_date_range_filter_narrows_the_list(): void
    {
        $this->order(['status' => OrderStatus::Confirmed, 'placed_at' => now()]);
        $this->order(['status' => OrderStatus::Confirmed, 'placed_at' => now()->subDays(40)]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['date_from' => now()->subDays(7)->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    }

    #[Test]
    public function the_region_filter_splits_local_from_international(): void
    {
        $this->order(['customer_country' => 'AE']);
        $this->order(['customer_country' => 'GB']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['region' => 'local']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)
                ->where('orders.data.0.country', 'AE'));

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['region' => 'international']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)
                ->where('orders.data.0.country', 'GB'));
    }

    #[Test]
    public function the_status_summary_counts_the_filtered_set(): void
    {
        $this->order(['status' => OrderStatus::Confirmed]);
        $this->order(['status' => OrderStatus::Confirmed]);
        $this->order(['status' => OrderStatus::Delivered]);
        $this->order(['status' => OrderStatus::Cancelled]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('statusSummary.confirmed', 2)
                ->where('statusSummary.delivered', 1)
                ->where('statusSummary.cancelled', 1)
                ->where('statusSummary.pending', 0));
    }

    #[Test]
    public function the_sales_cards_compute_realized_upcoming_and_total(): void
    {
        // Realized: paid (100) + delivered-unpaid (200) = 300.
        $this->order(['status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_PAID, 'total' => 100]);
        $this->order(['status' => OrderStatus::Delivered, 'payment_status' => Order::PAYMENT_UNPAID, 'total' => 200]);

        // Upcoming: confirmed-unpaid COD (500).
        $this->order(['status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_UNPAID, 'total' => 500]);

        // Excluded from both: cancelled (999) and failed (300).
        $this->order(['status' => OrderStatus::Cancelled, 'payment_status' => Order::PAYMENT_PAID, 'total' => 999]);
        $this->order(['status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_FAILED, 'total' => 300]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            // Whole amounts serialize to JSON integers, so compare as such.
            ->assertInertia(fn (Assert $page) => $page
                ->where('salesCards.realized', 300)
                ->where('salesCards.upcoming', 500)
                ->where('salesCards.total', 800));
    }

    #[Test]
    public function the_show_page_renders_items_payments_shipment_and_timeline(): void
    {
        $order = $this->order(['status' => OrderStatus::Confirmed]);
        $order->items()->create([
            'name' => 'Emerald Gown',
            'unit_price' => 499,
            'quantity' => 2,
            'line_total' => 998,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'reference' => 'PAY-'.$order->id,
            'gateway' => 'stripe',
            'amount' => 998,
            'currency' => 'AED',
            'status' => Payment::STATUS_PAID,
            'gateway_transaction_id' => 'txn_123',
        ]);
        Shipment::factory()->dispatched()->create(['order_id' => $order->id, 'invoice' => $order->order_number]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/show')
                ->where('order.order_number', $order->order_number)
                ->has('order.items', 1)
                ->has('order.payments', 1)
                ->where('order.payments.0.gateway_transaction_id', 'txn_123')
                ->has('order.admin_shipment')
                // At minimum the "placed" event opened the timeline.
                ->has('order.events')
                ->has('order.allowed_transitions'));
    }

    #[Test]
    public function an_admin_sees_any_order_regardless_of_owner(): void
    {
        $owner = User::factory()->create();
        $order = $this->order(['user_id' => $owner->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk();
    }

    #[Test]
    public function update_status_applies_a_legal_transition_and_records_an_event(): void
    {
        $order = $this->order(['status' => OrderStatus::Confirmed]);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.status', $order), ['to' => 'packed'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Packed, $order->refresh()->status);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'status_changed',
            'from_status' => 'confirmed',
            'to_status' => 'packed',
            'actor_type' => 'staff',
        ]);
    }

    #[Test]
    public function update_status_rejects_an_illegal_transition(): void
    {
        $order = $this->order(['status' => OrderStatus::Delivered]);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.status', $order), ['to' => 'pending'])
            ->assertSessionHasErrors('to');

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'type' => 'status_changed',
            'to_status' => 'pending',
        ]);
    }

    #[Test]
    public function cancelling_an_order_releases_stock_exactly_once(): void
    {
        $order = $this->order(['status' => OrderStatus::Confirmed]);

        $spy = Mockery::mock(CheckoutService::class);
        $spy->shouldReceive('releaseStock')->once()->with(Mockery::on(fn ($arg) => $arg->is($order)));
        $this->app->instance(CheckoutService::class, $spy);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.status', $order), ['to' => 'cancelled'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    #[Test]
    public function a_comment_writes_a_timeline_event(): void
    {
        $order = $this->order(['status' => OrderStatus::Confirmed]);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.comment', $order), ['body' => 'Called the customer to confirm sizing.'])
            ->assertRedirect();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'comment',
            'body' => 'Called the customer to confirm sizing.',
            'actor_type' => 'staff',
        ]);
    }

    #[Test]
    public function a_comment_requires_a_body(): void
    {
        $order = $this->order(['status' => OrderStatus::Confirmed]);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.comment', $order), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    #[Test]
    public function confirming_records_a_status_changed_event(): void
    {
        Queue::fake();
        $order = $this->order(['status' => OrderStatus::Pending]);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.confirm', $order))
            ->assertRedirect();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'status_changed',
            'from_status' => 'pending',
            'to_status' => 'confirmed',
        ]);
    }

    #[Test]
    public function dispatching_records_a_shipment_event(): void
    {
        Queue::fake();
        config()->set('delivery.default', 'steadfast');
        $order = $this->order(['status' => OrderStatus::Confirmed]);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.dispatch', $order))
            ->assertRedirect();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'shipment',
            'actor_type' => 'staff',
        ]);
    }

    #[Test]
    public function marking_an_order_paid_records_a_payment_event_once(): void
    {
        Queue::fake();
        $order = $this->order(['status' => OrderStatus::Pending, 'payment_status' => Order::PAYMENT_UNPAID]);

        $order->markPaid();
        $order->markPaid();

        $this->assertSame(1, $order->events()->where('type', 'payment')->count());
    }

    #[Test]
    public function an_order_opens_its_timeline_with_a_placed_event(): void
    {
        $order = $this->order();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'placed',
            'actor_type' => 'system',
        ]);
    }
}
