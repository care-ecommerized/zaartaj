<?php

namespace App\Http\Controllers\Admin;

use App\Delivery\ShipmentDispatcher;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Orders\OrderNotifier;
use App\Orders\OrderPresenter;
use App\Orders\OrderStateMachine;
use App\Reports\SalesReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin Orders console: a filterable list with a status summary and sales
 * cards, and a per-order detail page with a status workflow and activity
 * timeline. Fulfilment actions (confirm / dispatch) live here too and each
 * records a timeline event.
 */
class OrderController extends Controller
{
    /** The payment_status values a staff member can filter on. */
    private const PAYMENT_STATUSES = [
        Order::PAYMENT_UNPAID,
        Order::PAYMENT_PAID,
        Order::PAYMENT_FAILED,
        Order::PAYMENT_REFUNDED,
    ];

    public function __construct(
        private readonly ShipmentDispatcher $dispatcher,
        private readonly SalesReport $report,
        private readonly OrderStateMachine $machine,
        private readonly OrderPresenter $presenter,
    ) {}

    /**
     * The orders list: filters, a per-status summary strip, sales cards
     * (realized / upcoming / total) and a paginated table.
     */
    public function index(Request $request): Response
    {
        $orders = $this->filtered($request)
            ->with('shipment')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'country' => $order->customer_country,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'shipment' => $order->shipment === null ? null : [
                    'status' => $order->shipment->status->value,
                    'tracking_code' => $order->shipment->tracking_code,
                ],
            ]);

        // Realized / upcoming / total over the *filtered* set (not just this page).
        $realized = (float) $this->report->applyRealized($this->filtered($request))->sum('total');
        $upcoming = (float) $this->report->applyUpcoming($this->filtered($request))->sum('total');

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => [
                'status' => $request->query('status'),
                'payment_status' => $request->query('payment_status'),
                'search' => $request->query('search'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'region' => $request->query('region', 'all'),
            ],
            'statuses' => array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
            'paymentStatuses' => self::PAYMENT_STATUSES,
            'statusSummary' => $this->statusSummary($request),
            'salesCards' => [
                'realized' => $realized,
                'upcoming' => $upcoming,
                'total' => $realized + $upcoming,
            ],
            'baseCurrency' => (string) config('payment.currency'),
        ]);
    }

    /**
     * The order detail page: customer, address, items, totals, payments, the
     * shipment/tracking record, the allowed status transitions and the timeline.
     */
    public function show(Order $order): Response
    {
        return Inertia::render('admin/orders/show', [
            'order' => $this->presenter->presentForAdmin($order),
            'baseCurrency' => (string) config('payment.currency'),
        ]);
    }

    /**
     * Apply a manual status change through the state machine. Illegal
     * transitions are rejected; a legal one records a `status_changed` event.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'to' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $to = $validated['to'] instanceof OrderStatus ? $validated['to'] : OrderStatus::from($validated['to']);

        if (! $this->machine->canTransition($order->status, $to)) {
            throw ValidationException::withMessages([
                'to' => "Order {$order->order_number} cannot move to {$to->value} from {$order->status->value}.",
            ]);
        }

        $this->machine->apply($order, $to, $request->user()?->name);

        return back()->with('status', "Order {$order->order_number} is now {$to->value}.");
    }

    /**
     * Post a staff comment to the order timeline.
     */
    public function addComment(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        OrderEvent::record($order, 'comment', 'Comment', [
            'body' => $validated['body'],
            'actor_type' => 'staff',
            'actor_name' => $request->user()?->name,
        ]);

        return back()->with('status', 'Comment posted.');
    }

    /**
     * Confirm a (typically cash-on-delivery) order after staff have vetted it.
     *
     * Confirmation fires OrderConfirmed, which books the courier shipment when
     * automatic dispatch is on. Idempotent: an already-confirmed order is left
     * as it is — and only a real transition records a timeline event.
     */
    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $before = $order->status;

        $order->confirm();

        if ($before === OrderStatus::Pending && $order->status === OrderStatus::Confirmed) {
            OrderEvent::record($order, 'status_changed', 'Order confirmed', [
                'from_status' => $before->value,
                'to_status' => $order->status->value,
                'actor_type' => 'staff',
                'actor_name' => $request->user()?->name,
            ]);
        }

        return back()->with('status', "Order {$order->order_number} confirmed.");
    }

    /**
     * Mark a paid order as refunded and email the customer.
     *
     * This records the refund on the payment side (payment_status → refunded) and
     * on the timeline; it does NOT call the gateway (refund execution is out of
     * scope). Guarded to paid orders so an unpaid/failed order can't be "refunded".
     */
    public function markRefunded(Request $request, Order $order): RedirectResponse
    {
        if ($order->payment_status !== Order::PAYMENT_PAID) {
            throw ValidationException::withMessages([
                'refund' => "Order {$order->order_number} is not paid, so it cannot be refunded.",
            ]);
        }

        $order->forceFill(['payment_status' => Order::PAYMENT_REFUNDED])->save();

        OrderEvent::record($order, 'payment', 'Payment refunded', [
            'actor_type' => 'staff',
            'actor_name' => $request->user()?->name,
            'meta' => ['payment_status' => Order::PAYMENT_REFUNDED],
        ]);

        app(OrderNotifier::class)->refunded($order);

        return back()->with('status', "Order {$order->order_number} marked refunded.");
    }

    /**
     * Book (or retry) the courier shipment for an order by hand — for when
     * automatic dispatch is off, or a previous booking failed. Records a
     * `shipment` timeline event.
     */
    public function dispatchShipment(Request $request, Order $order): RedirectResponse
    {
        $shipment = $this->dispatcher->queueFor($order);

        OrderEvent::record($order, 'shipment', 'Shipment booked', [
            'actor_type' => 'staff',
            'actor_name' => $request->user()?->name,
            'meta' => [
                'courier' => $shipment->courier,
                'consignment_id' => $shipment->consignment_id,
            ],
        ]);

        return back()->with('status', $shipment->consignment_id
            ? "Order {$order->order_number} is already booked ({$shipment->consignment_id})."
            : "Booking queued for order {$order->order_number}.");
    }

    /**
     * Stream the filtered orders as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'orders-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Order', 'Customer', 'Email', 'Status', 'Payment', 'Total (AED)', 'Country', 'Placed at']);

            $this->filtered($request)
                ->orderByDesc('placed_at')
                ->orderByDesc('id')
                ->chunk(200, function ($orders) use ($out): void {
                    foreach ($orders as $order) {
                        fputcsv($out, [
                            $order->order_number,
                            $order->customer_name,
                            $order->customer_email,
                            $order->status->value,
                            $order->payment_status,
                            number_format((float) $order->total, 2, '.', ''),
                            $order->customer_country,
                            $order->placed_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * The shared, filtered orders query behind the list, the summary, the sales
     * cards and the CSV export — every filter in one place so they never drift.
     */
    private function filtered(Request $request): Builder
    {
        $local = strtoupper((string) config('checkout.local_country', 'AE'));

        return Order::query()
            ->when($request->query('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($request->query('payment_status'), fn (Builder $q, $payment) => $q->where('payment_status', $payment))
            ->when($request->query('search'), function (Builder $q, $term): void {
                $like = '%'.$term.'%';
                $q->where(function (Builder $w) use ($like): void {
                    $w->where('order_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_email', 'like', $like)
                        ->orWhere('customer_phone', 'like', $like);
                });
            })
            ->when($request->query('date_from'), fn (Builder $q, $from) => $q->whereDate('placed_at', '>=', $from))
            ->when($request->query('date_to'), fn (Builder $q, $to) => $q->whereDate('placed_at', '<=', $to))
            ->when($request->query('region'), function (Builder $q, $region) use ($local): void {
                if ($region === 'local') {
                    $q->where('customer_country', $local);
                } elseif ($region === 'international') {
                    $q->where('customer_country', '<>', $local);
                }
            });
    }

    /**
     * Per-status order counts for the current filter, keyed by status value.
     *
     * @return array<string, int>
     */
    private function statusSummary(Request $request): array
    {
        $counts = $this->filtered($request)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => (int) $counts->get($status->value, 0)])
            ->all();
    }
}
