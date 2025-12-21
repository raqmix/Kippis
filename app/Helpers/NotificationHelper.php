<?php

namespace App\Helpers;

use App\Core\Services\DatabaseNotificationService;
use App\Core\Services\FilamentNotificationService;

/**
 * Global Notification Helper
 * 
 * Provides a convenient facade for sending notifications
 * 
 * Usage:
 * - notify()->success('Title', 'Body') - Filament toast notifications
 * - dbNotify()->success('Title', 'Message', $admin, $url) - Database notifications
 */
if (!function_exists('notify')) {
    function notify(): FilamentNotificationService
    {
        return app(FilamentNotificationService::class);
    }
}

if (!function_exists('dbNotify')) {
    function dbNotify(): DatabaseNotificationService
    {
        return app(DatabaseNotificationService::class);
    }
}

