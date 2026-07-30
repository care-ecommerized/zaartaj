<?php

namespace App\Payments;

/**
 * Normalised outcome of a finalize/verify call, so callers never have to know
 * whether "Completed" (bKash) or "Success" (Nagad) means paid.
 */
class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $transactionId = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function success(?string $transactionId, array $raw = []): self
    {
        return new self(true, $transactionId, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failure(?string $message, array $raw = []): self
    {
        return new self(false, null, $message, $raw);
    }
}
