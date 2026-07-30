<?php

namespace App\Delivery\Enums;

enum DeliveryType: int
{
    /** Rider carries the parcel to the recipient's address. */
    case Home = 0;

    /** Recipient collects the parcel from a Steadfast point. */
    case Point = 1;
}
