<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregate sales figures for the admin dashboard.
 *
 * Every metric is a single grouped/aggregate query — no per-row loops — so the
 * dashboard stays cheap even as the orders table grows. Everything is scoped to
 * a named date range against `orders.placed_at` (default the last 30 days); the
 * catalogue/customer KPIs are all-time snapshots and are documented as such.
 *
 * ── Pinned "realized sales" predicate ─────────────────────────────────────────
 * An order contributes to Realized Sales when it is NOT cancelled, its payment
 * has NOT failed, and it has either been paid OR delivered:
 *
 *     status <> 'cancelled'
 *   AND payment_status <> 'failed'
 *   AND (payment_status = 'paid' OR status = 'delivered')
 *
 * This lives in applyRealized() and is the single source of truth — reuse it,
 * never re-inline the clause.
 *
 * ── Pipeline bucket mapping ───────────────────────────────────────────────────
 *   NEW        → Pending
 *   PROCESSING → Confirmed + Packed
 *   SHIPPED    → Shipped
 *   DELIVERED  → Delivered
 *   CANCELLED  → Cancelled + Returned
 */
class SalesReport
{
    /** The date-range keys the dashboard pills offer. */
    public const RANGES = ['today', '7d', '30d', '90d', 'year'];

    public const DEFAULT_RANGE = '30d';

    /**
     * The full dashboard payload for a named range.
     *
     * @return array{
     *     range: string,
     *     pipeline: array<string, int>,
     *     kpis: array{total_orders: int, realized_sales: float, active_products: int, customers: int},
     *     recentOrders: list<array<string, mixed>>
     * }
     */
    public function summary(string $range): array
    {
        $range = $this->normaliseRange($range);
        $from = $this->startFor($range);

        return [
            'range' => $range,
            'pipeline' => $this->pipeline($from),
            'kpis' => $this->kpis($from),
            'recentOrders' => $this->recentOrders(),
        ];
    }

    /**
     * Coerce an arbitrary range key to a supported one, defaulting to 30 days.
     */
    public function normaliseRange(string $range): string
    {
        return in_array($range, self::RANGES, true) ? $range : self::DEFAULT_RANGE;
    }

    /**
     * The inclusive lower bound (on placed_at) for a named range.
     */
    public function startFor(string $range): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return match ($this->normaliseRange($range)) {
            'today' => $now->startOfDay(),
            '7d' => $now->subDays(7),
            '90d' => $now->subDays(90),
            'year' => $now->startOfYear(),
            default => $now->subDays(30),
        };
    }

    /**
     * Order counts bucketed into the five pipeline stages, within the range.
     *
     * @return array{new: int, processing: int, shipped: int, delivered: int, cancelled: int}
     */
    public function pipeline(CarbonImmutable $from): array
    {
        $counts = Order::query()
            ->where('placed_at', '>=', $from)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        $count = fn (OrderStatus $status): int => (int) $counts->get($status->value, 0);

        return [
            'new' => $count(OrderStatus::Pending),
            'processing' => $count(OrderStatus::Confirmed) + $count(OrderStatus::Packed),
            'shipped' => $count(OrderStatus::Shipped),
            'delivered' => $count(OrderStatus::Delivered),
            'cancelled' => $count(OrderStatus::Cancelled) + $count(OrderStatus::Returned),
        ];
    }

    /**
     * The headline KPIs. Orders/sales are range-scoped; products and customers
     * are all-time snapshots (documented on the dashboard cards).
     *
     * @return array{total_orders: int, realized_sales: float, active_products: int, customers: int}
     */
    public function kpis(CarbonImmutable $from): array
    {
        return [
            'total_orders' => Order::query()->where('placed_at', '>=', $from)->count(),
            'realized_sales' => $this->realizedSales($from),
            'active_products' => Product::query()->published()->count(),
            // "Customers" = every non-admin account, all-time.
            'customers' => User::query()->where('is_admin', false)->count(),
        ];
    }

    /**
     * SUM(total) in AED base for realized orders within the range.
     */
    public function realizedSales(CarbonImmutable $from): float
    {
        return (float) $this->applyRealized(
            Order::query()->where('placed_at', '>=', $from)
        )->sum('total');
    }

    /**
     * The pinned realized-revenue predicate. Single source of truth — reuse it
     * (e.g. the admin Orders console sales cards), never re-inline the clause.
     */
    public function applyRealized(Builder $query): Builder
    {
        return $query
            ->where('status', '<>', OrderStatus::Cancelled->value)
            ->where('payment_status', '<>', Order::PAYMENT_FAILED)
            ->where(function (Builder $q): void {
                $q->where('payment_status', Order::PAYMENT_PAID)
                    ->orWhere('status', OrderStatus::Delivered->value);
            });
    }

    /**
     * The pinned realized predicate as a raw SQL boolean, for aggregate contexts
     * where a Builder where-chain does not fit — e.g. a conditional
     * `SUM(CASE WHEN <realized> THEN total ELSE 0 END)` when grouping the orders
     * table per customer. Mirrors applyRealized() exactly; it is the same clause
     * expressed as a fragment, so the two never drift. `$table` qualifies the
     * columns for queries that join other tables.
     */
    public function realizedExpression(string $table = 'orders'): string
    {
        return "({$table}.status <> '".OrderStatus::Cancelled->value."'"
            ." and {$table}.payment_status <> '".Order::PAYMENT_FAILED."'"
            ." and ({$table}.payment_status = '".Order::PAYMENT_PAID."'"
            ." or {$table}.status = '".OrderStatus::Delivered->value."'))";
    }

    /**
     * "Upcoming" sales: the complement of realized among orders still in play —
     * not cancelled or returned, payment not failed, and not yet paid-or-delivered.
     * These are COD/unpaid orders working their way through the pipeline.
     */
    public function applyUpcoming(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])
            ->where('payment_status', '<>', Order::PAYMENT_FAILED)
            ->where(function (Builder $q): void {
                $q->where('payment_status', '<>', Order::PAYMENT_PAID)
                    ->where('status', '<>', OrderStatus::Delivered->value);
            });
    }

    /**
     * The latest 8 orders, newest first, for the dashboard table.
     *
     * @return list<array<string, mixed>>
     */
    public function recentOrders(): array
    {
        return Order::query()
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['order_number', 'customer_name', 'status', 'payment_status', 'total', 'placed_at'])
            ->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'placed_at' => $order->placed_at?->toIso8601String(),
            ])
            ->all();
    }
}
