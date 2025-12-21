<?php

return [
    'data_retention' => [
        'activity_logs' => 365, // days
        'security_logs' => 730,
        'login_attempts' => 90,
        'audit_trails' => 2555, // 7 years for compliance
    ],
    
    'activity_log' => [
        'enabled' => true,
        'queue' => env('ACTIVITY_LOG_QUEUE', false),
        'queue_connection' => env('ACTIVITY_LOG_QUEUE_CONNECTION', 'default'),
    ],
    
    'security' => [
        'auto_lock_failed_attempts' => 5,
        'lockout_duration_minutes' => 15,
    ],
];
