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
    | Product media uploads
    |--------------------------------------------------------------------------
    |
    | Limits for the media an admin attaches by hand on the product screens.
    | Videos share the image disk unless pointed elsewhere — a clip is large
    | enough that an object store is often the better home for it.
    |
    | The video ceiling is only ever as high as PHP allows: `upload_max_filesize`
    | and `post_max_size` in php.ini have to be at least this large, or the
    | request is truncated before Laravel ever validates it.
    |
    */

    'video_disk' => env('CATALOG_VIDEO_DISK', env('CATALOG_IMAGE_DISK', 'public')),

    // Kilobytes, matching Laravel's `max:` rule.
    'image_max_kb' => (int) env('CATALOG_IMAGE_MAX_KB', 5120),
    'video_max_kb' => (int) env('CATALOG_VIDEO_MAX_KB', 102400),

    // How many images one upload request may carry.
    'image_batch_max' => (int) env('CATALOG_IMAGE_BATCH_MAX', 20),

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
        'jewellery' => ['jewelry', 'jewellery', 'necklace', 'earring', 'bracelet', 'bangle', 'brooch', 'tiara', 'anklet', 'pendant', 'ring', 'choker', 'chain'],
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
