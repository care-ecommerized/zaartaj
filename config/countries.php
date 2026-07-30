<?php

/*
|--------------------------------------------------------------------------
| Supported destination countries
|--------------------------------------------------------------------------
|
| ISO-3166-1 alpha-2 code => display name, offered in the checkout country
| picker. Every code here resolves to a shipping zone (a country-specific zone
| or the catch-all), so any of them is shippable. Add or remove entries to open
| or close a market at the storefront; pricing is still governed by the
| shipping_zones / shipping_rates tables.
|
*/

return [
    'AE' => 'United Arab Emirates',
    'SA' => 'Saudi Arabia',
    'QA' => 'Qatar',
    'KW' => 'Kuwait',
    'OM' => 'Oman',
    'BH' => 'Bahrain',
    'BD' => 'Bangladesh',
    'IN' => 'India',
    'PK' => 'Pakistan',
    'LK' => 'Sri Lanka',
    'NP' => 'Nepal',
    'MY' => 'Malaysia',
    'SG' => 'Singapore',
    'ID' => 'Indonesia',
    'GB' => 'United Kingdom',
    'US' => 'United States',
    'CA' => 'Canada',
    'AU' => 'Australia',
    'NZ' => 'New Zealand',
    'DE' => 'Germany',
    'FR' => 'France',
    'IT' => 'Italy',
    'ES' => 'Spain',
    'NL' => 'Netherlands',
    'SE' => 'Sweden',
    'NO' => 'Norway',
    'TR' => 'Türkiye',
    'EG' => 'Egypt',
    'JO' => 'Jordan',
    'LB' => 'Lebanon',
    'ZA' => 'South Africa',
    'JP' => 'Japan',
    'CN' => 'China',
    'HK' => 'Hong Kong',
];
