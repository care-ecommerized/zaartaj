<?php

namespace App\Payments;

use App\Checkout\CheckoutService;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\OrderNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Writes a payment's final outcome exactly once and keeps its order in step.
 *
 * Shared by the browser callback, the reconcile action, and every gateway
 * webhook — so however the news of a payment arrives, it settles the same way.
 */
class PaymentSettlementService
{
    public function __construct(private readonly CheckoutService $checkout) {}

    /**
     * Record the outcome under a row lock, skipping if already settled, so a
     * callback and a webhook (or a reconcile) racing each other cannot both apply.
     */
    public function settle(Payment $payment, PaymentResult $result): void
    {
        DB::transaction(function () use ($payment, $result) {
            $fresh = Payment::whereKey($payment->getKey())->lockForUpdate()->first();

            if (! $fresh || $fresh->isSettled()) {
                return;
            }

            $fresh->forceFill($result->successful ? [
                'status' => Payment::STATUS_PAID,
                'gateway_transaction_id' => $result->transactionId,
                'paid_at' => now(),
                'meta' => array_merge($fresh->meta ?? [], ['result' => $result->raw]),
            ] : [
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => $result->message,
                'meta' => array_merge($fresh->meta ?? [], ['result' => $result->raw]),
            ])->save();

            // Keep the caller's in-memory model current with what we just wrote.
            $payment->setRawAttributes($fresh->getAttributes(), true);

            if ($payment->order_id) {
                $this->reflectOnOrder($payment);
            }
        });
    }

    /**
     * Move the order in step with its payment.
     *
     * Paid: mark it paid and confirmed. Failed: mark the payment side failed and
     * hand the reserved stock back, so an abandoned online order does not sit on
     * inventory nobody paid for.
     */
    private function reflectOnOrder(Payment $payment): void
    {
        $order = $payment->order()->lockForUpdate()->first();

        if (! $order || $order->payment_status === Order::PAYMENT_PAID) {
            return;
        }

        if ($payment->isPaid()) {
            $order->markPaid();

            return;
        }

        $from = $order->status;

        $order->forceFill([
            'payment_status' => Order::PAYMENT_FAILED,
            'status' => OrderStatus::Cancelled,
        ])->save();

        $this->checkout->releaseStock($order);

        // A failed online payment cancels the order — let the customer know.
        app(OrderNotifier::class)->statusChanged($order, $from, OrderStatus::Cancelled);
    }
}
