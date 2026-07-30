<?php

namespace App\Delivery\Exceptions;

use RuntimeException;

class DeliveryException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function fromCourier(string $courier, string $message, array $context = []): self
    {
        return new self(sprintf(
            '[%s] %s%s',
            $courier,
            $message,
            $context ? ' '.json_encode($context) : ''
        ));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function rejected(string $courier, int $httpStatus, array $body): self
    {
        $message = $body['message'] ?? 'no message returned';

        // Steadfast puts the actionable detail (duplicate invoice, bad phone,
        // COD over limit) in `errors`, not in `message`.
        if (isset($body['errors']) && is_array($body['errors'])) {
            $message .= ' ('.collect($body['errors'])->flatten()->implode('; ').')';
        }

        return self::fromCourier($courier, "rejected the request [HTTP {$httpStatus}]: {$message}");
    }

    public static function notConfigured(string $courier): self
    {
        return self::fromCourier(
            $courier,
            "is missing credentials. Set its keys in config/delivery.php (couriers.{$courier})."
        );
    }

    /**
     * A capability the shared Courier contract exposes but this courier's API
     * has no equivalent for (e.g. Aramex has no COD-balance endpoint).
     */
    public static function unsupported(string $courier, string $feature): self
    {
        return self::fromCourier($courier, "does not support {$feature}.");
    }
}
