<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    /**
     * Whether the order is ready to be handed to a courier.
     */
    public function isDispatchable(): bool
    {
        return in_array($this, [self::Confirmed, self::Packed], true);
    }
}
