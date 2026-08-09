<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Gateway
    |--------------------------------------------------------------------------
    |
    | The gateway used when a payment does not explicitly name one.
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'bkash'),

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    |
    | The currency the catalogue is priced in and every order total is stored
    | in. Gateways that settle in another currency (see each gateway's own
    | 'currency' below) charge the converted equivalent. The AED-settling
    | gateways (stripe/tap/tabby/tamara) therefore need no conversion; bKash and
    | Nagad settle in BDT and convert out of the base.
    |
    */

    'currency' => 'AED',

    /*
    |--------------------------------------------------------------------------
    | Live FX Provider
    |--------------------------------------------------------------------------
    |
    | Base URL the `fx:update` job appends the base code to. open.er-api.com is
    | keyless and returns a base-to-target `rates` map. Overridable so a paid
    | provider can be swapped in without a code change.
    |
    */

    'fx_endpoint' => env('FX_ENDPOINT', 'https://open.er-api.com/v6/latest/'),

    /*
    |--------------------------------------------------------------------------
    | Exchange Rates (legacy fallback)
    |--------------------------------------------------------------------------
    |
    | LEGACY. The `currencies` table (App\Models\Currency) is now the source of
    | truth for exchange rates, and CurrencyService is the only converter. This
    | array is retained purely as a fallback some older code/tests may still read
    | and MUST NOT be treated as authoritative. Values here are the pre-AED
    | quote: base units per one target unit (1 AED = 33 BDT).
    |
    */

    'exchange_rates' => [
        'AED' => (float) env('FX_AED_TO_BDT', 33.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    |
    | Each entry needs a 'driver' (what the manager switches on) and a
    | 'currency' (what the customer is charged in). 'requires_order' marks a
    | gateway that needs full order context — buyer, items, shipping address —
    | so it is offered only in the storefront checkout, never the standalone
    | payment page.
    |
    */

    'gateways' => [

        'bkash' => [
            'driver' => 'bkash',
            'currency' => 'BDT',

            // sandbox: https://tokenized.sandbox.bka.sh/v1.2.0-beta
            // live:    https://tokenized.pay.bka.sh/v1.2.0-beta
            'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),

            'app_key' => env('BKASH_APP_KEY'),
            'app_secret' => env('BKASH_APP_SECRET'),
            'username' => env('BKASH_USERNAME'),
            'password' => env('BKASH_PASSWORD'),
        ],

        'nagad' => [
            'driver' => 'nagad',
            'currency' => 'BDT',

            // sandbox: http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0
            // live:    https://api.mynagad.com/remote-payment-gateway-1.0
            'base_url' => env('NAGAD_BASE_URL', 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0'),

            'merchant_id' => env('NAGAD_MERCHANT_ID'),

            // PEM bodies (no header/footer lines needed) issued by Nagad.
            'merchant_private_key' => env('NAGAD_MERCHANT_PRIVATE_KEY'),
            'nagad_public_key' => env('NAGAD_PUBLIC_KEY'),
        ],

        'stripe' => [
            'driver' => 'stripe',
            'currency' => env('STRIPE_CURRENCY', 'AED'),

            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),

            'secret_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        'tap' => [
            'driver' => 'tap',
            'currency' => env('TAP_CURRENCY', 'AED'),

            'base_url' => env('TAP_BASE_URL', 'https://api.tap.company'),

            'secret_key' => env('TAP_SECRET_KEY'),
            'publishable_key' => env('TAP_PUBLISHABLE_KEY'),
            'webhook_secret' => env('TAP_WEBHOOK_SECRET'),
            // Needed by the on-page Tap Card SDK; optional for the redirect flow.
            'merchant_id' => env('TAP_MERCHANT_ID'),
        ],

        'tabby' => [
            'driver' => 'tabby',
            'currency' => env('TABBY_CURRENCY', 'AED'),
            'requires_order' => true,

            'base_url' => env('TABBY_BASE_URL', 'https://api.tabby.ai'),

            'public_key' => env('TABBY_PUBLIC_KEY'),
            'secret_key' => env('TABBY_SECRET_KEY'),
            'merchant_code' => env('TABBY_MERCHANT_CODE'),
        ],

        'tamara' => [
            'driver' => 'tamara',
            'currency' => env('TAMARA_CURRENCY', 'AED'),
            'requires_order' => true,

            // sandbox: https://api-sandbox.tamara.co
            // live:    https://api.tamara.co
            'base_url' => env('TAMARA_BASE_URL', 'https://api-sandbox.tamara.co'),

            'api_token' => env('TAMARA_API_TOKEN'),
            'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
            'public_key' => env('TAMARA_PUBLIC_KEY'),

            // Tamara requires a two-letter country for scoring (UAE by default).
            'country' => env('TAMARA_COUNTRY', 'AE'),
        ],

    ],

];
