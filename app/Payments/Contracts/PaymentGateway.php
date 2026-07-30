<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentResult;

interface PaymentGateway
{
    /**
     * Gateway key as it appears in config('payment.gateways').
     */
    public function name(): string;

    /**
     * Register the payment with the gateway and return the URL the customer
     * must be sent to. Persists any gateway-side identifiers on the model.
     *
     * @throws PaymentException
     */
    public function initiate(Payment $payment): string;

    /**
     * Settle the payment once the customer returns from the gateway.
     *
     * @param  array<string, mixed>  $callback  Query/body parameters of the return request.
     */
    public function finalize(Payment $payment, array $callback): PaymentResult;

    /**
     * Ask the gateway for the authoritative status, ignoring anything the
     * browser told us. Used for reconciliation and for abandoned payments.
     */
    public function verify(Payment $payment): PaymentResult;
}
