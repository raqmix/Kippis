<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SquadPayment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAYING = 'paying';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_TIMED_OUT = 'timed_out';
    public const STATUS_REFUNDED = 'refunded';

    /** Statuses that block a fresh attempt for the same member. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAYING,
        self::STATUS_PAID,
    ];

    /** Statuses where the finalizer should include this member's items. */
    public const FINALIZE_STATUSES = [self::STATUS_PAID];

    /** Statuses the host can no longer cancel without triggering a refund. */
    public const CHARGED_STATUSES = [self::STATUS_PAID];

    protected $fillable = [
        'squad_session_id',
        'squad_member_id',
        'share_piasters',
        'status',
        'gateway_order_id',
        'mastercard_session_id',
        'mastercard_transaction_id',
        'refund_transaction_id',
        'refunded_at',
        'idempotency_key',
        'paid_at',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'share_piasters' => 'integer',
            'paid_at'        => 'datetime',
            'refunded_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            if (empty($payment->idempotency_key)) {
                $payment->idempotency_key = (string) Str::uuid();
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SquadSession::class, 'squad_session_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(SquadMember::class, 'squad_member_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isResolved(): bool
    {
        return ! in_array($this->status, [self::STATUS_PENDING, self::STATUS_PAYING], true);
    }
}
