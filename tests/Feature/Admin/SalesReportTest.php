<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Reports\SalesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function report(): SalesReport
    {
        return app(SalesReport::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes): Order
    {
        return Order::factory()->create(array_merge([
            'placed_at' => now(),
        ], $attributes));
    }

    #[Test]
    public function realized_sales_only_counts_paid_or_delivered_and_never_cancelled_or_failed(): void
    {
        // Counted: paid, and delivered-but-unpaid.
        $this->order(['status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_PAID, 'total' => 100]);
        $this->order(['status' => OrderStatus::Delivered, 'payment_status' => Order::PAYMENT_UNPAID, 'total' => 200]);

        // Excluded: cancelled (even if paid), unpaid-and-undelivered, failed, delivered-but-failed.
        $this->order(['status' => OrderStatus::Cancelled, 'payment_status' => Order::PAYMENT_PAID, 'total' => 999]);
        $this->order(['status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_UNPAID, 'total' => 500]);
        $this->order(['status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_FAILED, 'total' => 500]);
        $this->order(['status' => OrderStatus::Delivered, 'payment_status' => Order::PAYMENT_FAILED, 'total' => 300]);

        $from = $this->report()->startFor('today');

        $this->assertSame(300.0, $this->report()->realizedSales($from));
    }

    #[Test]
    public function the_pipeline_buckets_map_statuses_correctly(): void
    {
        $this->order(['status' => OrderStatus::Pending]);
        $this->order(['status' => OrderStatus::Pending]);
        $this->order(['status' => OrderStatus::Confirmed]);
        $this->order(['status' => OrderStatus::Packed]);
        $this->order(['status' => OrderStatus::Shipped]);
        $this->order(['status' => OrderStatus::Delivered]);
        $this->order(['status' => OrderStatus::Delivered]);
        $this->order(['status' => OrderStatus::Delivered]);
        $this->order(['status' => OrderStatus::Cancelled]);
        $this->order(['status' => OrderStatus::Returned]);

        $pipeline = $this->report()->pipeline($this->report()->startFor('today'));

        $this->assertSame([
            'new' => 2,
            'processing' => 2,
            'shipped' => 1,
            'delivered' => 3,
            'cancelled' => 2,
        ], $pipeline);
    }

    #[Test]
    public function the_range_bound_filters_out_older_orders(): void
    {
        $this->order(['status' => OrderStatus::Confirmed, 'placed_at' => now()]);
        $this->order(['status' => OrderStatus::Confirmed, 'placed_at' => now()->subDays(60)]);

        $summary = $this->report()->summary('30d');

        $this->assertSame('30d', $summary['range']);
        $this->assertSame(1, $summary['kpis']['total_orders']);

        // The 90-day window sees both.
        $this->assertSame(2, $this->report()->summary('90d')['kpis']['total_orders']);
    }

    #[Test]
    public function an_unknown_range_key_defaults_to_thirty_days(): void
    {
        $this->assertSame('30d', $this->report()->normaliseRange('bogus'));
        $this->assertSame('7d', $this->report()->normaliseRange('7d'));
    }
}
