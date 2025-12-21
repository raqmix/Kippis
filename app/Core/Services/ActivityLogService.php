<?php

namespace App\Core\Services;

use App\Core\Enums\ActivityAction;
use App\Core\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    public function log(
        ActivityAction $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $adminId = null,
        string $severity = 'info'
    ): ActivityLog {
        return ActivityLog::create([
            'admin_id' => $adminId ?? auth('admin')->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'severity' => $severity,
        ]);
    }
}
