<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledPushSend extends Model
{
    protected $fillable = [
        'scheduled_push_id',
        'window_at',
        'fired_at',
        'recipients_count',
        'sent_ok_count',
        'sent_failed_count',
    ];

    protected function casts(): array
    {
        return [
            'window_at' => 'datetime',
            'fired_at' => 'datetime',
            'recipients_count' => 'integer',
            'sent_ok_count' => 'integer',
            'sent_failed_count' => 'integer',
        ];
    }

    public function push()
    {
        return $this->belongsTo(ScheduledPush::class, 'scheduled_push_id');
    }
}
