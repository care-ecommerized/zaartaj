<?php

namespace App\Orders;

use App\Enums\OrderStatus;
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
     * The richer shape the admin order-detail console renders: everything the
     * customer sees, plus staff-only context — the payments ledger, the full
     * shipment/tracking record, the activity timeline, and the manual status
     * transitions staff may apply next.
     *
     * @return array<string, mixed>
     */
    public function presentForAdmin(Order $order): array
    {
        $order->loadMissing(['items', 'payments', 'shipment', 'events', 'user']);

        $machine = app(OrderStateMachine::class);
        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from((string) $order->status);

        return array_merge($this->present($order), [
            'id' => $order->id,
            'note' => $order->note,
            'base_currency' => $order->base_currency,
            'fx_rate' => (float) $order->fx_rate,
            'user' => $order->user === null ? null : [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ],
            'payments' => $order->payments->map(fn ($payment) => [
                'gateway' => $payment->gateway,
                'reference' => $payment->reference,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'gateway_transaction_id' => $payment->gateway_transaction_id,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->all(),
            'admin_shipment' => $this->adminShipment($order),
            'events' => $order->events->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->type,
                'title' => $event->title,
                'body' => $event->body,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'actor_type' => $event->actor_type,
                'actor_name' => $event->actor_name,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->all(),
            'allowed_transitions' => array_map(
                fn (OrderStatus $to) => $to->value,
                $machine->allowedTransitions($status),
            ),
            'can_confirm' => $status === OrderStatus::Pending,
            'is_dispatchable' => $status->isDispatchable(),
        ]);
    }

    /**
     * The full shipment/tracking record for staff — including the courier
     * consignment id the customer-facing summary omits.
     *
     * @return array<string, mixed>|null
     */
    private function adminShipment(Order $order): ?array
    {
        $shipment = $order->relationLoaded('shipment') ? $order->getRelation('shipment') : $order->shipment;

        if ($shipment === null) {
            return null;
        }

        return [
            'courier' => $shipment->courier,
            'consignment_id' => $shipment->consignment_id,
            'tracking_code' => $shipment->tracking_code,
            'status' => $shipment->status instanceof \BackedEnum ? $shipment->status->value : $shipment->status,
            'dispatched_at' => $shipment->dispatched_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
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
