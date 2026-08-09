<?php

namespace App\Orders;

use App\Checkout\CheckoutService;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use InvalidArgumentException;

/**
 * The allowed forward map for MANUAL admin status edits.
 *
 * Courier-driven advances (shipped/delivered/returned) keep flowing through the
 * delivery subsystem; this only governs what a human may set by hand from the
 * order detail page, and records a `status_changed` timeline event when they do.
 */
class OrderStateMachine
{
    /**
     * from → the statuses staff may move it to. Cancelled and Returned are
     * terminal (no outgoing edges).
     *
     * @var array<string, list<OrderStatus>>
     */
    private const TRANSITIONS = [
        'pending' => [OrderStatus::Confirmed, OrderStatus::Cancelled],
        'confirmed' => [OrderStatus::Packed, OrderStatus::Cancelled],
        'packed' => [OrderStatus::Shipped, OrderStatus::Cancelled],
        'shipped' => [OrderStatus::Delivered, OrderStatus::Returned],
        'delivered' => [OrderStatus::Returned],
        'returned' => [],
        'cancelled' => [],
    ];

    /**
     * The statuses staff may move an order to from a given state.
     *
     * @return list<OrderStatus>
     */
    public function allowedTransitions(OrderStatus $from): array
    {
        return self::TRANSITIONS[$from->value] ?? [];
    }

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions($from), true);
    }

    /**
     * Validate and apply a manual status change, recording a timeline event.
     *
     * Cancelling an unshipped order releases its reserved stock. Because Cancelled
     * is terminal and only reachable from unshipped states (pending/confirmed/
     * packed), this can run at most once per order — no double-release guard needed.
     */
    public function apply(Order $order, OrderStatus $to, ?string $actorName = null): Order
    {
        $from = $order->status;

        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException("Cannot move order {$order->order_number} from {$from->value} to {$to->value}.");
        }

        $order->forceFill(['status' => $to])->save();

        if ($to === OrderStatus::Cancelled) {
            app(CheckoutService::class)->releaseStock($order);
        }

        OrderEvent::record($order, 'status_changed', "Status changed to {$to->value}", [
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_type' => 'staff',
            'actor_name' => $actorName,
        ]);

        return $order;
    }
}
