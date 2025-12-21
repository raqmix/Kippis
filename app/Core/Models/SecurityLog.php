<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    protected $fillable = [
        'admin_id',
        'event_type',
        'severity',
        'ip_address',
        'user_agent',
        'description',
        'metadata',
        'resolved',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }

    public function resolve(?Admin $admin = null): void
    {
        $this->update([
            'resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $admin?->id,
        ]);
    }
}
