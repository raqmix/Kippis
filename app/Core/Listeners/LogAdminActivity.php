<?php

namespace App\Core\Listeners;

use App\Core\Events\AdminLoggedIn;
use App\Core\Events\AdminLoggedOut;
use App\Core\Services\ActivityLogService;
use App\Core\Enums\ActivityAction;

class LogAdminActivity
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }

    public function handleAdminLoggedIn(AdminLoggedIn $event): void
    {
        $this->activityLogService->log(
            ActivityAction::LOGIN,
            $event->admin,
            null,
            null,
            $event->admin->id
        );

        // Also log to file
        \Illuminate\Support\Facades\Log::channel('activity')->info('Admin logged in', [
            'admin_id' => $event->admin->id,
            'email' => $event->admin->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    public function handleAdminLoggedOut(AdminLoggedOut $event): void
    {
        $this->activityLogService->log(
            ActivityAction::LOGOUT,
            $event->admin,
            null,
            null,
            $event->admin->id
        );

        // Update the most recent login history with logout time
        \App\Core\Models\AdminLoginHistory::where('admin_id', $event->admin->id)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first()
            ?->update(['logout_at' => now()]);

        // Also log to file
        \Illuminate\Support\Facades\Log::channel('activity')->info('Admin logged out', [
            'admin_id' => $event->admin->id,
            'email' => $event->admin->email,
        ]);
    }
}

