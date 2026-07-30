<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once, when an order first transitions into the Confirmed state —
 * prepaid orders at payment settlement, cash-on-delivery orders when staff
 * confirm them. Booking a courier shipment hangs off this.
 */
class OrderConfirmed
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
