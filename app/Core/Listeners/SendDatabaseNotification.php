<?php

namespace App\Core\Listeners;

use App\Core\Events\AdminLoggedIn;
use App\Core\Events\FailedLoginAttempt;
use App\Core\Services\DatabaseNotificationService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/**
 * Listener for sending database notifications on various events
 * 
 * These notifications appear in Filament's topbar with Facebook-like UX
 */
class SendDatabaseNotification
{
    public function __construct(
        private DatabaseNotificationService $notificationService
    ) {
    }

    /**
     * Handle successful admin login
     */
    public function handleAdminLoggedIn(AdminLoggedIn $event): void
    {
        $this->notificationService->success(
            __('system.login_successful'),
            __('system.welcome_back', ['name' => $event->admin->name]),
            $event->admin
        );
    }

    /**
     * Handle failed login attempt
     * Only notify security admins, not the person who failed login
     */
    public function handleFailedLogin(FailedLoginAttempt $event): void
    {
        $admin = Auth::guard('admin')->user();
        
        if ($admin && $admin->can('view_security_logs')) {
            $this->notificationService->danger(
                __('system.failed_login_attempt'),
                __('system.invalid_credentials_attempted', ['email' => $event->email]),
                $admin
            );
        }
    }
}

