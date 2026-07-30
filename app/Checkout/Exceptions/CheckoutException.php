<?php

namespace App\Checkout\Exceptions;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    /**
     * Per-line problems, keyed by cart line index, for showing against the item.
     *
     * @var array<int, string>
     */
    public array $lineErrors = [];

    /**
     * @param  array<int, string>  $lineErrors
     */
    public static function withLineErrors(string $message, array $lineErrors = []): self
    {
        $exception = new self($message);
        $exception->lineErrors = $lineErrors;

        return $exception;
    }
}
