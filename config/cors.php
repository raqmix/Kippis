<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure CORS settings for your API. The paths and
    | allowed methods, headers and credentials may be modified as required.
    |
    */

    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('APP_URL', 'http://localhost'),
        'https://kippis.raversys.uk',
        'https://www.kippis.raversys.uk',
    ],

    'allowed_origins_patterns' => [
        env('CORS_ALLOWED_ORIGINS_PATTERNS', ''),
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept-Language',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];
