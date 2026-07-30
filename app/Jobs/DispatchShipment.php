<?php

namespace App\Jobs;

use App\Delivery\ShipmentDispatcher;
use App\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchShipment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Steadfast rate-limits bursts, so back off rather than hammering it.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    /**
     * Booking the same parcel twice costs real money. `send()` also refuses to
     * re-book a shipment that already has a consignment id; this just keeps a
     * duplicate job from queueing in the first place.
     */
    public int $uniqueFor = 600;

    public function __construct(public readonly Shipment $shipment) {}

    public function uniqueId(): string
    {
        return (string) $this->shipment->id;
    }

    public function handle(ShipmentDispatcher $dispatcher): void
    {
        $dispatcher->send($this->shipment);
    }
}
