<?php

return [
    'types' => [
        'pos' => [
            'label' => 'Point of Sale',
            'requires_credentials' => true,
        ],
        'payment' => [
            'label' => 'Payment Gateway',
            'requires_credentials' => true,
        ],
        'webhook' => [
            'label' => 'Webhook',
            'requires_credentials' => false,
        ],
        'api' => [
            'label' => 'API Integration',
            'requires_credentials' => true,
        ],
    ],
    
    'default_settings' => [
        'timeout' => 30,
        'retry_attempts' => 3,
    ],
];
