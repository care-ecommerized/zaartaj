<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\Exceptions\PaymentException;
use Illuminate\Http\Request;

interface HandlesWebhooks
{
    /**
     * Verify the webhook's authenticity and resolve which Payment it concerns.
     *
     * Returns the Payment so the caller can re-verify it with the gateway and
     * settle it, or null to acknowledge an event we do not act on. The signature
     * is the only thing trusted here — the amount/outcome is confirmed by a
     * follow-up verify() call, never taken from the webhook body.
     *
     * @throws PaymentException On an invalid signature.
     */
    public function handleWebhook(Request $request): ?Payment;
}
