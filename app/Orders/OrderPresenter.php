<?php

namespace App\Orders;

use App\Models\Order;
use Throwable;

/**
 * Shapes an order into the array both the checkout confirmation page and the
 * customer account order-detail page render.
 *
 * Money is stored in the base currency (AED); the raw base figures are exposed
 * for the existing confirmation view, plus the presentment currency and its
 * converted total for callers that show the charged amount.
 */
class OrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'order_number' => $order->order_number,
            'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
            'payment_method' => $order->payment_method instanceof \BackedEnum ? $order->payment_method->value : $order->payment_method,
            'payment_status' => $order->payment_status,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'customer_address' => $order->customer_address,
            'customer_district' => $order->customer_district,
            'customer_country' => $order->customer_country,
            'customer_postcode' => $order->customer_postcode,
            'subtotal' => (float) $order->subtotal,
            'shipping_total' => (float) $order->shipping_total,
            'discount_total' => (float) $order->discount_total,
            'coupon_code' => $order->coupon_code,
            'total' => (float) $order->total,
            'cod_amount' => (float) $order->cod_amount,
            // The presentment currency and the total expressed in it, so a page
            // can show exactly what the customer was charged. Falls back to the
            // base total when the currencies table cannot resolve a rate.
            'currency' => $order->currency,
            'presentment_total' => $this->presentmentTotal($order),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'variant_title' => $item->variant_title,
                'image' => $item->image,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => (float) $item->line_total,
            ])->all(),
            // A compact shipment/tracking summary when the relation is present.
            'shipment' => $this->shipment($order),
        ];
    }

    /**
     * The order total in its presentment currency, robust to an unseeded
     * currencies table (tests, fresh installs) where the rate cannot be read.
     */
    private function presentmentTotal(Order $order): float
    {
        try {
            return $order->presentmentTotal();
        } catch (Throwable) {
            return (float) $order->total;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shipment(Order $order): ?array
    {
        // Guard: the Shipment relation is optional and may be unloaded.
        if (! method_exists($order, 'shipment')) {
            return null;
        }

        $shipment = $order->relationLoaded('shipment') ? $order->getRelation('shipment') : $order->shipment;

        if ($shipment === null) {
            return null;
        }

        return [
            'courier' => $shipment->courier,
            'tracking_code' => $shipment->tracking_code,
            'status' => $shipment->status instanceof \BackedEnum ? $shipment->status->value : $shipment->status,
            'dispatched_at' => $shipment->dispatched_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
        ];
    }
}
