<?php

namespace App\Core\Listeners;

use App\Core\Events\FailedLoginAttempt;
use App\Core\Services\SecurityLogService;

class LogSecurityEvent
{
    public function __construct(
        private SecurityLogService $securityLogService
    ) {
    }

    public function handle(FailedLoginAttempt $event): void
    {
        $this->securityLogService->log(
            'failed_login',
            "Failed login attempt for email: {$event->email}",
            null,
            'medium',
            [
                'email' => $event->email,
                'ip_address' => $event->ipAddress,
                'user_agent' => $event->userAgent,
                'reason' => $event->reason,
            ]
        );

        // Also log to file
        \Illuminate\Support\Facades\Log::channel('security')->warning('Failed login attempt', [
            'email' => $event->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'reason' => $event->reason,
        ]);
    }
}

