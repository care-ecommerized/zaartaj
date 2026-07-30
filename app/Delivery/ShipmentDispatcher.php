<?php

namespace App\Delivery;

use App\Delivery\Enums\DeliveryType;
use App\Delivery\Enums\ShipmentStatus;
use App\Delivery\Exceptions\DeliveryException;
use App\Enums\OrderStatus;
use App\Jobs\DispatchShipment;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The seam between orders and couriers: owns every write to `shipments` so the
 * courier drivers stay stateless and the controllers stay thin.
 */
class ShipmentDispatcher
{
    public function __construct(private readonly DeliveryManager $couriers) {}

    /**
     * Get (or create) the draft shipment for an order without contacting the
     * courier. The (courier, invoice) unique key makes this idempotent, so an
     * order confirmed twice still has exactly one shipment row.
     */
    public function draftFor(Order $order, ?string $courier = null): Shipment
    {
        $courier = $this->courierFor($order, $courier);

        return Shipment::firstOrCreate(
            ['courier' => $courier, 'invoice' => $order->order_number],
            [
                'order_id' => $order->id,
                'status' => ShipmentStatus::Draft,
                'cod_amount' => $order->cod_amount,
                'delivery_type' => DeliveryType::from(
                    (int) config("delivery.couriers.{$courier}.default_delivery_type", 0)
                ),
                'note' => $order->note,
            ],
        );
    }

    /**
     * Get (or create) the shipment for an order and hand it to the courier now,
     * synchronously.
     *
     * Safe to call twice: the second call returns the existing consignment
     * rather than booking the parcel again.
     */
    public function dispatchFor(Order $order, ?string $courier = null): Shipment
    {
        return $this->send($this->draftFor($order, $courier));
    }

    /**
     * Draft the shipment and queue its booking for a worker to carry out.
     *
     * This is the seam order confirmation uses: the draft row is written in the
     * caller's transaction, and the courier call happens off-request. Returns the
     * draft, or the existing shipment untouched if it is already booked or settled.
     */
    public function queueFor(Order $order, ?string $courier = null): Shipment
    {
        $shipment = $this->draftFor($order, $courier);

        if ($shipment->consignment_id === null && ! $shipment->status->isFinal()) {
            // The default `database` queue is transactional: this job row is
            // written in the caller's transaction and only becomes visible to a
            // worker once that transaction commits, so it can never reference a
            // shipment that was rolled back. If you move to a non-transactional
            // queue (redis/sqs), set `after_commit => true` on its connection.
            DispatchShipment::dispatch($shipment);
        }

        return $shipment;
    }

    /**
     * Book a drafted (or previously failed) shipment with its courier.
     */
    public function send(Shipment $shipment): Shipment
    {
        // Already at the courier — re-sending would book a second parcel.
        if ($shipment->consignment_id !== null) {
            return $shipment;
        }

        $shipment->increment('attempts');

        try {
            $result = $this->couriers->driver($shipment->courier)
                ->createShipment(ShipmentRequest::fromShipment($shipment));
        } catch (DeliveryException $e) {
            Log::error('Shipment dispatch failed', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            $shipment->forceFill([
                'status' => ShipmentStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        if (! $result->successful) {
            $shipment->forceFill([
                'status' => ShipmentStatus::Failed,
                'failure_reason' => $result->message,
                'last_response' => $result->raw,
            ])->save();

            return $shipment;
        }

        $shipment->forceFill([
            'consignment_id' => $result->consignmentId,
            'tracking_code' => $result->trackingCode,
            'status' => $result->status,
            'provider_status' => $result->providerStatus,
            'failure_reason' => null,
            'dispatched_at' => now(),
            'last_synced_at' => now(),
            'last_response' => $result->raw,
        ])->save();

        $this->advanceOrder($shipment->order, OrderStatus::Shipped);

        return $shipment;
    }

    /**
     * Ask the courier for the authoritative status. Used to reconcile parcels
     * whose webhook never arrived.
     */
    public function sync(Shipment $shipment): Shipment
    {
        if ($shipment->consignment_id === null) {
            return $shipment;
        }

        $tracking = $this->couriers->driver($shipment->courier)
            ->trackByConsignmentId($shipment->consignment_id);

        return $this->applyStatus($shipment, $tracking->status, $tracking->providerStatus, $tracking->raw);
    }

    /**
     * Record a status the courier reported, from a webhook or a lookup.
     *
     * @param  array<string, mixed>  $raw
     */
    public function applyStatus(
        Shipment $shipment,
        ShipmentStatus $status,
        ?string $providerStatus = null,
        array $raw = [],
    ): Shipment {
        DB::transaction(function () use ($shipment, $status, $providerStatus, $raw) {
            $fresh = Shipment::whereKey($shipment->getKey())->lockForUpdate()->first();

            if (! $fresh) {
                return;
            }

            // Couriers re-send webhooks and can deliver them out of order; a
            // parcel that already settled must not be walked backwards.
            if ($fresh->status->isFinal()) {
                return;
            }

            $fresh->forceFill([
                'status' => $status,
                'provider_status' => $providerStatus,
                'last_synced_at' => now(),
                'last_response' => $raw ?: $fresh->last_response,
                'delivered_at' => $status === ShipmentStatus::Delivered ? now() : $fresh->delivered_at,
            ])->save();

            $shipment->setRawAttributes($fresh->getAttributes(), true);
        });

        if ($orderStatus = $shipment->status->toOrderStatus()) {
            $this->advanceOrder($shipment->order, $orderStatus);
        }

        return $shipment;
    }

    /**
     * Decide which courier carries an order.
     *
     * An explicit courier (staff choosing by hand) always wins. Otherwise routing
     * is by destination: Bangladesh ships with the domestic courier (Steadfast),
     * everywhere else with the international one (Aramex). If the chosen courier is
     * not configured, the global default is used so an order is never un-shippable.
     */
    private function courierFor(Order $order, ?string $courier): string
    {
        if ($courier !== null) {
            return $courier;
        }

        // A factory/new order that never set a country carries the BD table
        // default; treat a missing value as BD rather than mis-routing abroad.
        $country = strtoupper((string) ($order->customer_country ?? 'BD'));
        $preferred = $country === 'BD' ? 'steadfast' : 'aramex';

        if (config("delivery.couriers.{$preferred}") !== null) {
            return $preferred;
        }

        return (string) config('delivery.default');
    }

    /**
     * Move the order forward, never backward — a late "shipped" webhook must
     * not undo a delivery that already landed.
     */
    private function advanceOrder(?Order $order, OrderStatus $status): void
    {
        if (! $order || in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            return;
        }

        $order->forceFill(['status' => $status])->save();
    }
}
