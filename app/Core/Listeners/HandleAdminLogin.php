<?php

namespace App\Core\Listeners;

use App\Core\Events\AdminLoggedIn;
use App\Core\Events\FailedLoginAttempt;
use App\Core\Models\Admin;
use App\Core\Models\AdminLoginHistory;
use App\Core\Models\LoginAttempt;
use Illuminate\Auth\Events\Login;

class HandleAdminLogin
{
    public function handle(Login $event): void
    {
        // Only handle login for admin guard
        if ($event->guard === 'admin' && $event->user instanceof Admin) {
            $admin = $event->user;
            $ipAddress = request()->ip();
            $userAgent = request()->userAgent();

            // Create login history
            AdminLoginHistory::create([
                'admin_id' => $admin->id,
                'login_at' => now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'success' => true,
            ]);

            // Create login attempt record
            LoginAttempt::create([
                'email' => $admin->email,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'success' => true,
                'attempted_at' => now(),
            ]);

            // Fire AdminLoggedIn event
            event(new AdminLoggedIn($admin, $ipAddress, $userAgent));
        }
    }
}

