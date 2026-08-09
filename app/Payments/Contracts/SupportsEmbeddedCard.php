<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\Exceptions\PaymentException;

interface SupportsEmbeddedCard
{
    /**
     * Prepare an embedded (on-page) card payment and return the client-side
     * config the browser SDK needs to collect and confirm the card itself.
     *
     * The card number never touches our server — the SDK tokenises it inside
     * the provider's iframe. Persists any gateway id on the payment.
     *
     * @return array<string, mixed> e.g. ['provider' => 'stripe', 'client_secret' => '…', 'publishable_key' => '…']
     *
     * @throws PaymentException
     */
    public function prepareEmbedded(Payment $payment): array;
}
