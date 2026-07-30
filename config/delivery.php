<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Courier
    |--------------------------------------------------------------------------
    |
    | The courier used when a shipment does not explicitly name one.
    |
    */

    'default' => env('DELIVERY_COURIER', 'steadfast'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Dispatch
    |--------------------------------------------------------------------------
    |
    | When true, confirming an order queues a courier booking automatically
    | (prepaid orders on payment, cash-on-delivery orders when staff confirm
    | them). Turn it off to book every parcel by hand from the admin instead.
    |
    */

    'auto_dispatch' => env('DELIVERY_AUTO_DISPATCH', true),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | COD amounts are settled in BDT only.
    |
    */

    'currency' => 'BDT',

    /*
    |--------------------------------------------------------------------------
    | Couriers
    |--------------------------------------------------------------------------
    */

    'couriers' => [

        'steadfast' => [
            'driver' => 'steadfast',

            // Steadfast serves the same API on two hostnames; portal.packzy.com
            // is the legacy one and still live. There is no sandbox — test with
            // a real account and cancel the consignments you create.
            'base_url' => env('STEADFAST_BASE_URL', 'https://portal.steadfast.com.bd/api/v1'),

            'api_key' => env('STEADFAST_API_KEY'),
            'secret_key' => env('STEADFAST_SECRET_KEY'),

            // Steadfast authenticates its status push with this bearer token.
            'webhook_token' => env('STEADFAST_WEBHOOK_TOKEN'),

            'timeout' => env('STEADFAST_TIMEOUT', 15),
            'retries' => env('STEADFAST_RETRIES', 2),

            // 0 = home delivery, 1 = customer collects from a Steadfast point.
            'default_delivery_type' => env('STEADFAST_DELIVERY_TYPE', 0),

            // Steadfast caps a bulk create at 500 consignments per call.
            'bulk_limit' => 500,
        ],

        'aramex' => [
            'driver' => 'aramex',

            // test:  https://ws.dev.aramex.net
            // live:  https://ws.aramex.net
            'base_url' => env('ARAMEX_BASE_URL', 'https://ws.dev.aramex.net'),

            // Sent in the body of every request (Aramex has no header auth).
            'client_info' => [
                'UserName' => env('ARAMEX_USERNAME'),
                'Password' => env('ARAMEX_PASSWORD'),
                'Version' => env('ARAMEX_VERSION', 'v1.0'),
                'AccountNumber' => env('ARAMEX_ACCOUNT_NUMBER'),
                'AccountPin' => env('ARAMEX_ACCOUNT_PIN'),
                'AccountEntity' => env('ARAMEX_ACCOUNT_ENTITY'),
                'AccountCountryCode' => env('ARAMEX_ACCOUNT_COUNTRY_CODE', 'BD'),
                'Source' => env('ARAMEX_SOURCE', 24),
            ],

            // Fixed origin / warehouse party. Aramex needs a shipper on every
            // parcel; an order only carries the recipient.
            'shipper' => [
                'name' => env('ARAMEX_SHIPPER_NAME'),
                'company' => env('ARAMEX_SHIPPER_COMPANY'),
                'phone' => env('ARAMEX_SHIPPER_PHONE'),
                'email' => env('ARAMEX_SHIPPER_EMAIL'),
                'line1' => env('ARAMEX_SHIPPER_LINE1'),
                'city' => env('ARAMEX_SHIPPER_CITY'),
                'postcode' => env('ARAMEX_SHIPPER_POSTCODE'),
                'country_code' => env('ARAMEX_SHIPPER_COUNTRY_CODE', 'BD'),
            ],

            // Parcels to this country ship domestic (DOM); anywhere else is EXP.
            'origin_country' => env('ARAMEX_ORIGIN_COUNTRY', 'BD'),

            // Product types are account-specific — confirm with Aramex.
            'default_product_type_dom' => env('ARAMEX_PRODUCT_TYPE_DOM', 'OND'),
            'default_product_type_exp' => env('ARAMEX_PRODUCT_TYPE_EXP', 'PPX'),

            'default_weight' => env('ARAMEX_DEFAULT_WEIGHT', 0.5),
            'default_pieces' => env('ARAMEX_DEFAULT_PIECES', 1),
            'default_description' => env('ARAMEX_DEFAULT_DESCRIPTION', 'Merchandise'),
            'default_customs_value' => env('ARAMEX_DEFAULT_CUSTOMS_VALUE', 0),

            'timeout' => env('ARAMEX_TIMEOUT', 20),
            'retries' => env('ARAMEX_RETRIES', 2),
        ],

    ],

];
