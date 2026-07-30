<?php

namespace App\Console\Commands;

use App\Delivery\Exceptions\DeliveryException;
use App\Delivery\ShipmentDispatcher;
use App\Models\Shipment;
use Illuminate\Console\Command;

/**
 * Safety net for parcels whose webhook never arrived.
 */
class ReconcileShipments extends Command
{
    protected $signature = 'shipments:reconcile {--limit=200 : Maximum shipments to check in one run}';

    protected $description = 'Re-check unsettled shipments against their courier';

    public function handle(ShipmentDispatcher $dispatcher): int
    {
        $shipments = Shipment::trackable()
            ->orderBy('last_synced_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $failures = 0;

        foreach ($shipments as $shipment) {
            try {
                $dispatcher->sync($shipment);
            } catch (DeliveryException $e) {
                $failures++;
                $this->error("Shipment {$shipment->id}: {$e->getMessage()}");
            }
        }

        $this->info("Reconciled {$shipments->count()} shipment(s), {$failures} failed.");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
