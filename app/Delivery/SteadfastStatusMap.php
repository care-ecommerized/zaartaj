<?php

namespace App\Delivery;

use App\Delivery\Enums\ShipmentStatus;

/**
 * Translates Steadfast's delivery_status vocabulary into our canonical one.
 *
 * The `*_approval_pending` family means a rider reported an outcome that
 * Steadfast's operations team has not signed off yet — the parcel is not
 * settled, so none of those map to a final status.
 */
class SteadfastStatusMap
{
    private const MAP = [
        'pending' => ShipmentStatus::Pending,
        'in_review' => ShipmentStatus::InReview,
        'hold' => ShipmentStatus::Hold,
        'delivered_approval_pending' => ShipmentStatus::AwaitingApproval,
        'partial_delivered_approval_pending' => ShipmentStatus::AwaitingApproval,
        'cancelled_approval_pending' => ShipmentStatus::AwaitingApproval,
        'unknown_approval_pending' => ShipmentStatus::AwaitingApproval,
        'delivered' => ShipmentStatus::Delivered,
        'partial_delivered' => ShipmentStatus::PartiallyDelivered,
        'cancelled' => ShipmentStatus::Cancelled,
        'unknown' => ShipmentStatus::Unknown,
    ];

    public static function toShipmentStatus(?string $providerStatus): ShipmentStatus
    {
        if ($providerStatus === null) {
            return ShipmentStatus::Unknown;
        }

        return self::MAP[strtolower(trim($providerStatus))] ?? ShipmentStatus::Unknown;
    }
}
