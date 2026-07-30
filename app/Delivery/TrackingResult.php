<?php

namespace App\Delivery;

use App\Delivery\Enums\ShipmentStatus;

class TrackingResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ShipmentStatus $status,
        public readonly ?string $providerStatus = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromSteadfast(array $body): self
    {
        $providerStatus = $body['delivery_status'] ?? null;

        return new self(
            status: SteadfastStatusMap::toShipmentStatus($providerStatus),
            providerStatus: $providerStatus,
            raw: $body,
        );
    }

    /**
     * Build from an Aramex tracking result: the array of update rows for a
     * single AWB. The status comes from the most recent update's `UpdateCode`.
     *
     * @param  list<array<string, mixed>>  $updates
     */
    public static function fromAramex(array $updates): self
    {
        // Aramex returns updates oldest-first; the last row is the current state.
        $latest = ! empty($updates) ? end($updates) : [];
        $code = $latest['UpdateCode'] ?? null;
        $description = $latest['UpdateDescription'] ?? null;

        return new self(
            status: AramexStatusMap::toShipmentStatus($code, $description),
            // Keep the code as the raw provider status; the description is in raw.
            providerStatus: $code !== null ? (string) $code : ($description ?: null),
            raw: $latest ?: [],
        );
    }
}
