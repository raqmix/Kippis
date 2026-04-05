<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Apple Pay Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials required for Apple Pay web merchant session validation
    | and payment processing. These are obtained from the Apple Developer
    | Portal after registering a Merchant ID and generating certificates.
    |
    | 1. Register a Merchant ID at https://developer.apple.com (e.g. merchant.com.kippis)
    | 2. Create a Merchant Identity Certificate — used to validate merchant sessions
    |    for web payments. Export as .pem + .key and store in storage/certs/.
    | 3. Create a Payment Processing Certificate — used by your payment gateway
    |    (MPGS) to decrypt the Apple Pay token. Upload the CSR from MPGS directly.
    | 4. Download the apple-developer-merchantid-domain-association file from the
    |    portal and place it at public/.well-known/apple-developer-merchantid-domain-association
    | 5. Set the env variables below and ensure the domain is verified in the portal.
    |
    */

    'merchant_id'       => env('APPLE_PAY_MERCHANT_ID'),
    'merchant_cert_path' => env('APPLE_PAY_MERCHANT_CERT_PATH', storage_path('certs/apple_pay_merchant.pem')),
    'merchant_key_path'  => env('APPLE_PAY_MERCHANT_KEY_PATH',  storage_path('certs/apple_pay_merchant.key')),

    // The domain for which Apple Pay is enabled (must be verified in Apple Developer Portal)
    'domain_name'   => env('APPLE_PAY_DOMAIN', env('APP_DOMAIN', 'kippis.com')),

    // Display name shown on the Apple Pay sheet
    'display_name'  => env('APPLE_PAY_DISPLAY_NAME', 'Kippis'),
];
