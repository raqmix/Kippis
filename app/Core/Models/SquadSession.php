<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SquadSession extends Model
{
    protected $fillable = [
        'host_id', 'store_id', 'invite_code', 'status',
        'locked_at', 'order_id', 'expires_at',
        'payment_deadline_at', 'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'locked_at'           => 'datetime',
            'expires_at'          => 'datetime',
            'payment_deadline_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'host_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(SquadMember::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(SquadCartItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SquadPayment::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAwaitingPayments(): bool
    {
        return in_array($this->status, ['awaiting_payments', 'partially_paid'], true);
    }

    /**
     * The active payment row for a member (pending/paying/paid), if any.
     * Inactive rows (failed/skipped/timed_out/refunded) are history — a
     * retry creates a brand-new active row.
     */
    public function activePaymentForMember(int $memberId): ?SquadPayment
    {
        return $this->payments()
            ->where('squad_member_id', $memberId)
            ->whereIn('status', SquadPayment::ACTIVE_STATUSES)
            ->first();
    }

    /** Members whose share is paid and should be included in the finalized order. */
    public function paidMemberIds(): array
    {
        return $this->payments()
            ->where('status', SquadPayment::STATUS_PAID)
            ->pluck('squad_member_id')
            ->all();
    }

    /**
     * True when every payment row has resolved (paid / failed / skipped /
     * timed_out / refunded). Triggers the auto-finalize path.
     */
    public function allPaymentsResolved(): bool
    {
        return ! $this->payments()
            ->whereIn('status', [SquadPayment::STATUS_PENDING, SquadPayment::STATUS_PAYING])
            ->exists();
    }
}
