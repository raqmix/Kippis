<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRedeemWallet extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_APPLIED   = 'applied';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_REFUNDED  = 'refunded';

    protected $table = 'customer_redeem_wallet';

    protected $fillable = [
        'customer_id',
        'redeem_item_id',
        'points_spent',
        'status',
        'expires_at',
        'used_order_id',
        'used_at',
        'title_snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'points_spent'         => 'integer',
            'expires_at'           => 'datetime',
            'used_at'              => 'datetime',
            'title_snapshot_json'  => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function redeemItem(): BelongsTo
    {
        return $this->belongsTo(RedeemItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'used_order_id');
    }

    public function getTitle(string $locale = 'en'): string
    {
        $snap = $this->title_snapshot_json ?? [];
        return (string) ($snap[$locale] ?? $snap['en'] ?? $snap['ar']
            ?? $this->redeemItem?->getTitle($locale)
            ?? '');
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }
}
