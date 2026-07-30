<?php

namespace App\Delivery;

use App\Delivery\Enums\ShipmentStatus;
use Illuminate\Support\Str;

/**
 * Translates an Aramex tracking update into our canonical ShipmentStatus.
 *
 * Aramex's `UpdateCode` values are account/region-specific and must be confirmed
 * against the live account, so classification leans on the human-readable
 * `UpdateDescription` (stable English) as the primary signal, with a small
 * code-override map for the codes we are sure of. Anything unrecognised falls
 * through to `Unknown` — deliberately NOT a final state — so the parcel stays in
 * `Shipment::trackable()` and reconcile keeps polling rather than settling on a
 * status we did not actually understand.
 */
class AramexStatusMap
{
    /**
     * `UpdateCode` overrides, checked before the description. Empty by design:
     * Aramex's codes are account/region-specific and unverified here, so we
     * classify on the stable English description instead. Populate this once the
     * live account's code list is confirmed and a code needs to win over its
     * description text.
     *
     * @var array<string, ShipmentStatus>
     */
    private const CODE_MAP = [];

    /**
     * Ordered description matchers. First hit wins, so the more specific /
     * terminal phrases are listed before the broad in-transit ones.
     *
     * @var array<string, ShipmentStatus>
     */
    private const DESCRIPTION_MAP = [
        'returned to shipper' => ShipmentStatus::Cancelled,
        'return to shipper' => ShipmentStatus::Cancelled,
        'return to origin' => ShipmentStatus::Cancelled,
        'cancelled' => ShipmentStatus::Cancelled,

        'delivered' => ShipmentStatus::Delivered,
        'proof of delivery' => ShipmentStatus::Delivered,

        'hold' => ShipmentStatus::Hold,

        'out for delivery' => ShipmentStatus::InTransit,
        'in transit' => ShipmentStatus::InTransit,
        'picked up' => ShipmentStatus::InTransit,
        'shipment received' => ShipmentStatus::InTransit,
        'received at' => ShipmentStatus::InTransit,
        'departed' => ShipmentStatus::InTransit,
        'arrived' => ShipmentStatus::InTransit,
    ];

    public static function toShipmentStatus(?string $code, ?string $description = null): ShipmentStatus
    {
        if ($code !== null && isset(self::CODE_MAP[strtoupper(trim($code))])) {
            return self::CODE_MAP[strtoupper(trim($code))];
        }

        if ($description !== null && $description !== '') {
            $haystack = strtolower($description);

            foreach (self::DESCRIPTION_MAP as $needle => $status) {
                // "not delivered" / "attempted delivery" must not read as delivered.
                if ($needle === 'delivered' && Str::contains($haystack, ['not delivered', 'attempted', 'undelivered'])) {
                    continue;
                }

                if (str_contains($haystack, $needle)) {
                    return $status;
                }
            }
        }

        return ShipmentStatus::Unknown;
    }
}
