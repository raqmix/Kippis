<?php

return [
    'gateway' => env('MASTERCARD_GATEWAY_URL', 'https://test-gateway.mastercard.com'),
    'api_version' => env('MASTERCARD_API_VERSION', '100'),
    'pay_api_version' => env('MASTERCARD_PAY_API_VERSION', '100'),
    'merchant_id' => env('MASTERCARD_MERCHANT_ID'),
    'api_username' => env('MASTERCARD_API_USERNAME'),
    'api_password' => env('MASTERCARD_API_PASSWORD'),
    'currency' => env('MASTERCARD_CURRENCY', 'EGP'),
];
