<?php

namespace App\Delivery;

use App\Delivery\Enums\ShipmentStatus;

/**
 * Normalised outcome of a consignment creation, so callers never have to know
 * that Steadfast calls a new parcel "in_review".
 */
class ShipmentResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly string $invoice,
        public readonly ?string $consignmentId = null,
        public readonly ?string $trackingCode = null,
        public readonly ShipmentStatus $status = ShipmentStatus::Failed,
        public readonly ?string $providerStatus = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Build from a Steadfast `consignment` object.
     *
     * @param  array<string, mixed>  $consignment
     */
    public static function fromSteadfastConsignment(array $consignment, string $fallbackInvoice): self
    {
        $providerStatus = $consignment['status'] ?? null;

        return new self(
            successful: true,
            invoice: (string) ($consignment['invoice'] ?? $fallbackInvoice),
            consignmentId: isset($consignment['consignment_id'])
                ? (string) $consignment['consignment_id']
                : null,
            trackingCode: $consignment['tracking_code'] ?? null,
            status: SteadfastStatusMap::toShipmentStatus($providerStatus),
            providerStatus: $providerStatus,
            raw: $consignment,
        );
    }

    /**
     * Build from an Aramex `Shipments` result row.
     *
     * Aramex issues one AWB number (`ID`) that serves as both the booking id and
     * the tracking number, and returns no delivery status on creation — the
     * parcel is simply registered until the first tracking update lands.
     *
     * @param  array<string, mixed>  $shipment
     */
    public static function fromAramexShipment(array $shipment, string $fallbackInvoice): self
    {
        $awb = isset($shipment['ID']) ? (string) $shipment['ID'] : null;

        return new self(
            successful: true,
            invoice: (string) ($shipment['Reference1'] ?? $fallbackInvoice),
            consignmentId: $awb,
            trackingCode: $awb,
            status: ShipmentStatus::Pending,
            providerStatus: null,
            raw: $shipment,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failure(string $invoice, ?string $message, array $raw = []): self
    {
        return new self(
            successful: false,
            invoice: $invoice,
            message: $message,
            raw: $raw,
        );
    }
}
