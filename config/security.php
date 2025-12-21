<?php

return [
    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'prevent_reuse' => 5,
        'expiry_days' => 90,
    ],
    
    'login' => [
        'max_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'require_2fa' => env('REQUIRE_2FA', false),
    ],
    
    'session' => [
        'timeout' => 30, // minutes
        'max_concurrent' => 3,
    ],
    
    'ip' => [
        'enable_whitelist' => env('ENABLE_IP_WHITELIST', false),
        'enable_blacklist' => env('ENABLE_IP_BLACKLIST', true),
        'auto_block_failed_attempts' => 10,
        'block_duration' => 3600,
    ],
    
    'encryption' => [
        'channel_credentials' => true,
        'api_keys' => true,
        'webhook_secrets' => true,
    ],
    
    'monitoring' => [
        'enable_anomaly_detection' => true,
        'alert_on_critical' => true,
        'alert_email' => env('SECURITY_ALERT_EMAIL'),
    ],
];
