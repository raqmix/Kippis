<?php

return [
    'base_url' => env('FOODICS_BASE_URL', 'https://api.foodics.com'),

    'oauth' => [
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

