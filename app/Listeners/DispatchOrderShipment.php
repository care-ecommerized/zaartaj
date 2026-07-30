<?php

namespace App\Listeners;

use App\Delivery\ShipmentDispatcher;
use App\Events\OrderConfirmed;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Books a courier shipment when an order is confirmed.
 *
 * Runs synchronously so the draft shipment is written in the same transaction
 * that confirmed the order; the actual courier call is queued from there. A
 * failure to draft/queue must never undo a confirmed (and possibly paid) order,
 * so everything is guarded — the scheduled reconcile and the admin re-dispatch
 * action are the safety nets if this does not get the parcel booked.
 */
class DispatchOrderShipment
{
    public function __construct(private readonly ShipmentDispatcher $dispatcher) {}

    public function handle(OrderConfirmed $event): void
    {
        if (! config('delivery.auto_dispatch', true)) {
            return;
        }

        try {
            $this->dispatcher->queueFor($event->order);
        } catch (Throwable $e) {
            Log::error('Auto-dispatch on order confirmation failed', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
