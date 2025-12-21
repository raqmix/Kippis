<?php

namespace App\Core\Listeners;

use App\Core\Events\AdminLoggedIn;
use App\Core\Events\FailedLoginAttempt;
use App\Core\Services\FilamentNotificationService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/**
 * Listener for sending Filament notifications on various events
 */
class SendFilamentNotification
{
    public function __construct(
        private FilamentNotificationService $notificationService
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
            $event->admin,
            false // Don't persist login success notifications
        );
    }

    /**
     * Handle failed login attempt
     */
    public function handleFailedLogin(FailedLoginAttempt $event): void
    {
        // Only notify security admins, not the person who failed login
        // This is a security best practice
        $admin = Auth::guard('admin')->user();
        
        if ($admin && $admin->can('view_security_logs')) {
            $this->notificationService->danger(
                __('system.failed_login_attempt'),
                __('system.invalid_credentials_attempted', ['email' => $event->email]),
                $admin,
                true // Persist for security monitoring
            );
        }
    }
}

