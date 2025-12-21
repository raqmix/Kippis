<?php

namespace App\Core\Traits;

use App\Core\Models\ActivityLog;
use App\Core\Enums\ActivityAction;

trait HasActivityLogs
{
    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'model');
    }

    public function logActivity(ActivityAction $action, ?array $oldValues = null, ?array $newValues = null, ?int $adminId = null): void
    {
        $this->activityLogs()->create([
            'admin_id' => $adminId ?? auth('admin')->id(),
            'action' => $action->value,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ]);
    }
}
