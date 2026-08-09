<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | Bangladesh is priced as two zones: inside Dhaka city and everywhere else.
    | The customer's district decides which flat rate applies. Orders at or above
    | the free-shipping threshold ship at no charge — the site header already
    | promises free delivery over ৳15,000, so that promise is enforced here.
    |
    */

    'shipping' => [
        'inside_dhaka' => env('SHIPPING_INSIDE_DHAKA', 60),
        'outside_dhaka' => env('SHIPPING_OUTSIDE_DHAKA', 120),
        'free_over' => env('SHIPPING_FREE_OVER', 15000),

        // Phase C: shipping is now priced by admin-defined zones (see the
        // shipping_zones / shipping_rates tables and ShippingCalculator). This
        // AED figure is only the last-resort charge when no zone or rate can be
        // resolved for a destination, so a checkout can never fail to price.
        'fallback' => env('SHIPPING_FALLBACK', 80),
    ],

    /*
    | Districts billed at the inside-Dhaka rate. Matched case-insensitively against
    | the district the customer selects.
    */

    'dhaka_districts' => ['dhaka'],

    /*
    |--------------------------------------------------------------------------
    | Payment methods offered at checkout
    |--------------------------------------------------------------------------
    |
    | Order here is the order shown to the customer. Remove one to withdraw it.
    | 'cod' is cash on delivery; the others map to configured payment gateways.
    |
    */

    'payment_methods' => ['cod', 'bkash', 'nagad', 'tap', 'tabby', 'tamara'],

    /*
    | The base/home country, as an ISO-3166 alpha-2 code. Orders shipping here are
    | "local"; everywhere else is "international". Drives the admin Orders console
    | region toggle.
    */

    'local_country' => env('CHECKOUT_LOCAL_COUNTRY', 'AE'),

    /*
    | Cap on a single order, as a guard against a runaway cart. In whole Taka.
    */

    'max_order_total' => env('CHECKOUT_MAX_TOTAL', 1000000),

    /*
    | How long an open checkout session may sit idle before the hourly sweep
    | (`checkouts:sweep`) marks it abandoned and sends a recovery reminder.
    */

    'abandon_after_hours' => env('CHECKOUT_ABANDON_AFTER_HOURS', 4),

    /*
    | The 64 districts of Bangladesh, for the checkout district picker.
    */

    'districts' => [
        'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura', 'Brahmanbaria',
        'Chandpur', 'Chapainawabganj', 'Chattogram', 'Chuadanga', 'Cumilla', "Cox's Bazar",
        'Dhaka', 'Dinajpur', 'Faridpur', 'Feni', 'Gaibandha', 'Gazipur', 'Gopalganj',
        'Habiganj', 'Jamalpur', 'Jashore', 'Jhalokati', 'Jhenaidah', 'Joypurhat', 'Khagrachhari',
        'Khulna', 'Kishoreganj', 'Kurigram', 'Kushtia', 'Lakshmipur', 'Lalmonirhat', 'Madaripur',
        'Magura', 'Manikganj', 'Meherpur', 'Moulvibazar', 'Munshiganj', 'Mymensingh', 'Naogaon',
        'Narail', 'Narayanganj', 'Narsingdi', 'Natore', 'Netrokona', 'Nilphamari', 'Noakhali',
        'Pabna', 'Panchagarh', 'Patuakhali', 'Pirojpur', 'Rajbari', 'Rajshahi', 'Rangamati',
        'Rangpur', 'Satkhira', 'Shariatpur', 'Sherpur', 'Sirajganj', 'Sunamganj', 'Sylhet',
        'Tangail', 'Thakurgaon',
    ],

];
