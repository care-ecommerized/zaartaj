<?php

namespace App\Delivery\Contracts;

use App\Delivery\Exceptions\DeliveryException;
use App\Delivery\ShipmentRequest;
use App\Delivery\ShipmentResult;
use App\Delivery\TrackingResult;

interface Courier
{
    /**
     * Courier key as it appears in config('delivery.couriers').
     */
    public function name(): string;

    /**
     * Hand a single parcel to the courier.
     *
     * @throws DeliveryException
     */
    public function createShipment(ShipmentRequest $request): ShipmentResult;

    /**
     * Hand several parcels over in one call. Results come back in the same
     * order as the requests. Couriers report per-parcel failures inline rather
     * than failing the batch, so callers must check each result's flag.
     *
     * @param  list<ShipmentRequest>  $requests
     * @return list<ShipmentResult>
     *
     * @throws DeliveryException
     */
    public function createShipments(array $requests): array;

    /**
     * Ask the courier for the authoritative status, ignoring anything a
     * webhook told us. Used for reconciliation.
     *
     * @throws DeliveryException
     */
    public function trackByConsignmentId(string $consignmentId): TrackingResult;

    /**
     * @throws DeliveryException
     */
    public function trackByInvoice(string $invoice): TrackingResult;

    /**
     * COD balance the courier is currently holding for us, in BDT.
     *
     * @throws DeliveryException
     */
    public function balance(): float;
}
