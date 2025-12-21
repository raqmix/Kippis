<?php

namespace App\Core\Services;

use App\Core\Models\SecurityLog;

class SecurityLogService
{
    public function log(
        string $eventType,
        string $description,
        ?int $adminId = null,
        string $severity = 'medium',
        ?array $metadata = null
    ): SecurityLog {
        return SecurityLog::create([
            'admin_id' => $adminId,
            'event_type' => $eventType,
            'severity' => $severity,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
