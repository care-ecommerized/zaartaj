<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product image storage
    |--------------------------------------------------------------------------
    |
    | Where MirrorProductImage copies images pulled off a supplier CDN. This has
    | to be a publicly readable disk — the default filesystem disk is `local`,
    | which lives in storage/app/private and is deliberately not web-accessible,
    | so mirroring there produces URLs that 404.
    |
    | Run `php artisan storage:link` once for the `public` disk to be served.
    |
    */

    'image_disk' => env('CATALOG_IMAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Storefront categories
    |--------------------------------------------------------------------------
    |
    | The shop's own navigation, in the order it should appear. Supplier exports
    | carry Shopify's taxonomy ("Apparel & Accessories > Clothing > Dresses"),
    | which runs to dozens of nodes and is not how this shop wants to be browsed,
    | so imported products are mapped onto this fixed set instead. The original
    | taxonomy string is still kept on the product for reference.
    |
    | Add a category here and it appears in the navigation immediately, empty
    | until something is mapped into it.
    |
    */

    'categories' => [
        ['slug' => 'gowns', 'name' => 'Gowns'],
        ['slug' => 'modest-clothes', 'name' => 'Modest Clothes'],
        ['slug' => 'jewellery', 'name' => 'Jewellery'],
        ['slug' => 'bags', 'name' => 'Bags'],
        ['slug' => 'shoes', 'name' => 'Shoes'],
    ],

    /*
    | Keywords matched against a product's taxonomy path, type, tags and title.
    |
    | Evaluated top to bottom and the first hit wins, so the narrow categories are
    | listed before 'gowns' — "bridal shoes" should land in Shoes, and only fall
    | through to Gowns because nothing more specific matched.
    */

    'category_rules' => [
        'shoes' => ['shoe', 'footwear', 'heel', 'sandal', 'boot', 'slipper', 'khussa'],
        'bags' => ['bag', 'clutch', 'purse', 'handbag', 'tote', 'satchel', 'wallet'],
        'jewellery' => ['jewelry', 'jewellery', 'necklace', 'earring', 'bracelet', 'bangle', 'brooch', 'tiara', 'anklet', 'pendant'],
        'modest-clothes' => ['abaya', 'hijab', 'burqa', 'burkha', 'kaftan', 'caftan', 'jilbab', 'khimar', 'modest', 'niqab', 'shalwar', 'kurta'],
        'gowns' => ['gown', 'dress', 'skirt', 'bridal', 'wedding', 'costume', 'lehenga', 'saree', 'sari'],
    ],

    /*
    | Where a product lands when no rule matches. Null leaves it uncategorised,
    | which keeps it off the category pages but still visible in the full listing
    | and flagged in the admin — better than silently filing it somewhere wrong.
    */

    'fallback_category' => null,

];
