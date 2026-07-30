<?php

namespace App\Delivery\Enums;

use App\Enums\OrderStatus;

/**
 * Courier-agnostic shipment status.
 *
 * Couriers each use their own vocabulary, so the raw string always stays on
 * `shipments.provider_status` and this enum is what business logic reads.
 */
enum ShipmentStatus: string
{
    /** Created locally, not yet handed to the courier. */
    case Draft = 'draft';

    /** The courier rejected the consignment, or the call never landed. */
    case Failed = 'failed';

    case Pending = 'pending';
    case InReview = 'in_review';

    /** The courier has the parcel and it is moving through their network. */
    case InTransit = 'in_transit';

    case Hold = 'hold';

    /** The courier reported an outcome that its operators have not signed off yet. */
    case AwaitingApproval = 'awaiting_approval';

    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    /**
     * Whether the shipment has reached a state the courier will not move it out of.
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::PartiallyDelivered,
            self::Cancelled,
        ], true);
    }

    /**
     * The order status this shipment state implies, if it implies one.
     */
    public function toOrderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::Delivered, self::PartiallyDelivered => OrderStatus::Delivered,
            self::Cancelled => OrderStatus::Returned,
            default => null,
        };
    }
}
