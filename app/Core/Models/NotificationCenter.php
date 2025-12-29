<?php

namespace App\Core\Models;

use App\Helpers\ArabicTimeHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationCenter extends Model
{
    use SoftDeletes;

    protected $table = 'notifications_center';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'icon',
        'color',
        'is_read',
        'read_at',
        'action_url',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the admin user that owns the notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }

    /**
     * Scope to get notifications for a specific user (including global)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Admin|int|null $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $user)
    {
        $userId = $user instanceof Admin ? $user->id : $user;
        
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereNull('user_id'); // Global notifications
        });
    }

    /**
     * Get human-readable Arabic time
     * 
     * @return string
     */
    public function getArabicTimeAttribute(): string
    {
        return ArabicTimeHelper::diffForHumans($this->created_at);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }
}

