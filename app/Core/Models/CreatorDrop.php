<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorDrop extends Model
{
    protected $fillable = [
        'creator_id', 'product_id', 'title_en', 'title_ar',
        'description_en', 'description_ar', 'cover_image',
        'starts_at', 'ends_at', 'status', 'notify_before_minutes',
        'notification_sent', 'max_quantity', 'quantity_sold',
        'promo_code_id', 'store_ids',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'           => 'datetime',
            'ends_at'             => 'datetime',
            'store_ids'           => 'array',
            'notification_sent'   => 'boolean',
            'max_quantity'        => 'integer',
            'quantity_sold'       => 'integer',
            'notify_before_minutes' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->where('starts_at', '>', now());
    }

    public function isAvailableAtStore(?int $storeId): bool
    {
        if ($this->store_ids === null) {
            return true; // all stores
        }
        return in_array($storeId, $this->store_ids, true);
    }
}
