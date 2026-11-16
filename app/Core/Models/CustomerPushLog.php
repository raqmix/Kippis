<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One push attempt to a customer: sent, skipped (with reason) or failed. */
class CustomerPushLog extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $table = 'customer_push_log';

    protected $fillable = [
        'customer_id', 'type', 'category', 'status', 'reason',
        'dedup_key', 'attempt_key', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
