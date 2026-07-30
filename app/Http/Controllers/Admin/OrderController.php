<?php

namespace App\Http\Controllers\Admin;

use App\Delivery\ShipmentDispatcher;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class OrderController extends Controller
{
    public function __construct(private readonly ShipmentDispatcher $dispatcher) {}

    /**
     * Confirm a (typically cash-on-delivery) order after staff have vetted it.
     *
     * Confirmation fires OrderConfirmed, which books the courier shipment when
     * automatic dispatch is on. Idempotent: an already-confirmed order is left
     * as it is.
     */
    public function confirm(Order $order): RedirectResponse
    {
        $order->confirm();

        return back()->with('status', "Order {$order->order_number} confirmed.");
    }

    /**
     * Book (or retry) the courier shipment for an order by hand — for when
     * automatic dispatch is off, or a previous booking failed.
     */
    public function dispatchShipment(Order $order): RedirectResponse
    {
        $shipment = $this->dispatcher->queueFor($order);

        return back()->with('status', $shipment->consignment_id
            ? "Order {$order->order_number} is already booked ({$shipment->consignment_id})."
            : "Booking queued for order {$order->order_number}.");
    }
}
