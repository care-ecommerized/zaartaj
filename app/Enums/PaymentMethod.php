<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Stripe = 'stripe';
    case Tap = 'tap';
    case Tabby = 'tabby';
    case Tamara = 'tamara';

    /**
     * Whether the customer pays through an online gateway rather than on delivery.
     */
    public function isOnline(): bool
    {
        return $this !== self::CashOnDelivery;
    }

    /**
     * The payment gateway driver this method maps to, or null for cash on delivery.
     */
    public function gateway(): ?string
    {
        return match ($this) {
            self::CashOnDelivery => null,
            self::Bkash => 'bkash',
            self::Nagad => 'nagad',
            self::Stripe => 'stripe',
            self::Tap => 'tap',
            self::Tabby => 'tabby',
            self::Tamara => 'tamara',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on delivery',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Stripe => 'Credit card',
            self::Tap => 'Card / Apple Pay (Tap)',
            self::Tabby => 'Tabby — pay in 4',
            self::Tamara => 'Tamara — pay later',
        };
    }
}
