<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Build Your Mix — Foodics product
    |--------------------------------------------------------------------------
    |
    | The Kippis product id that represents the "Build Your Mix" product synced
    | from Foodics. Its Foodics modifier groups are the build-your-mix sections
    | (Base Modifiers, Sweetener, Flavour, Topper). The mix screen and the order
    | push both key off this product. Set MIX_FOODICS_PRODUCT_ID in .env.
    |
    */

    'foodics_product_id' => env('MIX_FOODICS_PRODUCT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Build Your Mix — Foodics product UUID (per-branch lookup)
    |--------------------------------------------------------------------------
    |
    | The Foodics-side product UUID for the BYM product. With per-branch menu
    | groups, /mix/options resolves the BYM by (uuid + selected store) via the
    | product_store pivot — each branch sees its own BYM row's modifier groups.
    |
    | Falls back to the legacy MIX_FOODICS_PRODUCT_ID lookup when this is
    | unset or no store_id was passed.
    |
    */

    'foodics_product_uuid' => env('MIX_FOODICS_PRODUCT_UUID'),

];
