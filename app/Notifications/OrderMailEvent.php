<?php

namespace App\Notifications;

use App\Enums\OrderStatus;

/**
 * The order lifecycle moments that email the customer.
 *
 * Each case names a translation group under `email.order.{value}.*` (subject,
 * greeting, intro, outro) so the notification copy is fully localized.
 */
enum OrderMailEvent: string
{
    case Placed = 'placed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    /**
     * The event a status transition maps to, or null when a move should not
     * email the customer (e.g. pending → confirmed is silent; placed covers it).
     */
    public static function forStatus(OrderStatus $status): ?self
    {
        return match ($status) {
            OrderStatus::Cancelled => self::Cancelled,
            OrderStatus::Returned => self::Returned,
            OrderStatus::Shipped => self::Shipped,
            OrderStatus::Delivered => self::Delivered,
            default => null,
        };
    }

    /**
     * Whether this email should carry the itemized invoice summary. Only the
     * order-placed email doubles as the invoice; the rest are short status notes.
     */
    public function showsInvoice(): bool
    {
        return $this === self::Placed;
    }
}
