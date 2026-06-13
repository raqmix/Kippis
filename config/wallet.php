<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wallet pass integration
    |--------------------------------------------------------------------------
    |
    | Apple Wallet (PassKit) + Google Wallet (Wallet Objects) for the
    | loyalty membership card. Updates fan out within 60s of state
    | change through APNs (Apple) and the LoyaltyObject.patch API
    | (Google). Each provider stays disabled until its credentials are
    | present so the scaffolding can ship before the certs land.
    |
    */

    'apple' => [
        'enabled' => env('WALLET_APPLE_ENABLED', false),

        /*
         | Pass Type ID as registered in the Apple Developer portal.
         | Example: pass.com.raqmix.kippis.loyalty
         */
        'pass_type_id' => env('WALLET_APPLE_PASS_TYPE_ID'),

        /*
         | Team ID (10 chars). Used inside pass.json's teamIdentifier
         | and to sign APNs JWTs.
         */
        'team_id' => env('WALLET_APPLE_TEAM_ID', '24ZU3YJF3K'),

        /*
         | Pass Type ID signing certificate (.p12 export from Keychain)
         | plus its export password and the WWDR intermediate.
         | Paths are absolute or storage_path() relative.
         */
        'pass_cert_path' => env('WALLET_APPLE_PASS_CERT_PATH'),
        'pass_cert_password' => env('WALLET_APPLE_PASS_CERT_PASSWORD'),
        'wwdr_cert_path' => env('WALLET_APPLE_WWDR_CERT_PATH'),

        /*
         | APNs auth key for push updates (60s SLA on state change).
         */
        'apns_key_path' => env('WALLET_APPLE_APNS_KEY_PATH'),
        'apns_key_id' => env('WALLET_APPLE_APNS_KEY_ID'),

        /*
         | Pass design — front colors. Stored as rgb() strings per the
         | PassKit spec. Background is brand purple to match the app.
         */
        'design' => [
            'organization_name' => env('WALLET_APPLE_ORG_NAME', 'Kippis'),
            'description' => env('WALLET_APPLE_DESCRIPTION', 'Kippis Rewards'),
            'logo_text' => env('WALLET_APPLE_LOGO_TEXT', 'Kippis'),
            'background_color' => env('WALLET_APPLE_BG', 'rgb(124, 58, 237)'),
            'foreground_color' => env('WALLET_APPLE_FG', 'rgb(255, 255, 255)'),
            'label_color' => env('WALLET_APPLE_LABEL', 'rgb(220, 220, 230)'),
        ],

        /*
         | Asset directory holding logo/icon/strip images. Pre-built at
         | each pass-build time; we never re-render PNGs at request time.
         */
        'assets_dir' => env('WALLET_APPLE_ASSETS_DIR', resource_path('wallet/apple')),

        /*
         | Public URL the wallet web service hits. Used as the
         | `webServiceURL` field inside pass.json.
         */
        'web_service_url' => env('WALLET_APPLE_WEBSERVICE_URL', env('APP_URL') . '/api'),
    ],

    'google' => [
        'enabled' => env('WALLET_GOOGLE_ENABLED', false),

        /*
         | Issuer id from https://pay.google.com/business/console/.
         */
        'issuer_id' => env('WALLET_GOOGLE_ISSUER_ID'),

        /*
         | Service account JSON path. Must have "Wallet Object Writer"
         | role on the Google Cloud project AND be linked as a
         | Developer/Admin on the Pay & Wallet console issuer.
         */
        'service_account_path' => env('WALLET_GOOGLE_SERVICE_ACCOUNT_PATH'),

        /*
         | Loyalty class id — namespaced under issuer. Bump the
         | suffix (`_v2`, etc.) when the class design changes.
         */
        'class_suffix' => env('WALLET_GOOGLE_CLASS_SUFFIX', 'kippis_loyalty_v1'),

        'design' => [
            'program_name' => env('WALLET_GOOGLE_PROGRAM_NAME', 'Kippis Rewards'),
            'program_logo_url' => env('WALLET_GOOGLE_LOGO_URL'),
            'background_hex' => env('WALLET_GOOGLE_BG_HEX', '#7C3AED'),
        ],
    ],
];
