<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderEvent;

class OrderObserver
{
    /**
     * Open the timeline with a "placed" event the moment an order is created.
     *
     * Runs inside whatever transaction created the order (e.g. CheckoutService),
     * so a rolled-back checkout never leaves an orphan event behind.
     */
    public function created(Order $order): void
    {
        OrderEvent::record($order, 'placed', 'Order placed', [
            'actor_type' => 'system',
            'to_status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
        ]);
    }
}
