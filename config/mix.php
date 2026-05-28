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

];
