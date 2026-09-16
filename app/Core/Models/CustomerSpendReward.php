<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerSpendReward extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_PICKED = 'picked';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    /** Non-terminal statuses — voucher still consumable by the customer. */
    public const ACTIVE_STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_PICKED,
        self::STATUS_APPLIED,
    ];

    protected $fillable = [
        'customer_id',
        'spend_reward_tier_id',
        'threshold_snapshot_piasters',
        'cycle_index',
        'status',
        'picks_json',
        'cart_id',
        'order_id',
        'issued_at',
        'expires_at',
        'picked_at',
        'applied_at',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold_snapshot_piasters' => 'integer',
            'cycle_index' => 'integer',
            'picks_json' => 'array',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'picked_at' => 'datetime',
            'applied_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tier()
    {
        return $this->belongsTo(SpendRewardTier::class, 'spend_reward_tier_id');
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** available | picked | applied — voucher still consumable. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeExpiring(Builder $query, ?\DateTimeInterface $before = null): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', $before ?? now());
    }

    public function isConsumable(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
