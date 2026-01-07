<?php

return [
    // Mode: 'sandbox' or 'live'
    'mode' => env('FOODICS_MODE', 'live'),

    // Base URLs for different environments
    'base_urls' => [
        'sandbox' => env('FOODICS_SANDBOX_BASE_URL', 'https://api-sandbox.foodics.com'),
        'live' => env('FOODICS_LIVE_BASE_URL', 'https://api.foodics.com'),
    ],

    // Legacy support - will use current mode
    'base_url' => env('FOODICS_BASE_URL'),

    'oauth' => [
        // Sandbox credentials
        'sandbox' => [
            'client_id' => env('FOODICS_SANDBOX_CLIENT_ID'),
            'client_secret' => env('FOODICS_SANDBOX_CLIENT_SECRET'),
        ],
        // Live credentials
        'live' => [
            'client_id' => env('FOODICS_LIVE_CLIENT_ID', env('FOODICS_CLIENT_ID')),
            'client_secret' => env('FOODICS_LIVE_CLIENT_SECRET', env('FOODICS_CLIENT_SECRET')),
        ],
        // Legacy support
        'client_id' => env('FOODICS_CLIENT_ID'),
        'client_secret' => env('FOODICS_CLIENT_SECRET'),
        'grant_type' => env('FOODICS_GRANT_TYPE', 'client_credentials'),
    ],

    'scopes' => env('FOODICS_SCOPES', ''),

    'timeout' => env('FOODICS_TIMEOUT', 30),

    'retry' => [
        'max_attempts' => env('FOODICS_RETRY_MAX_ATTEMPTS', 3),
        'delay_seconds' => env('FOODICS_RETRY_DELAY_SECONDS', 2),
    ],
];

