<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'valid_from',
        'valid_to',
        'usage_limit',
        'usage_per_user_limit',
        'used_count',
        'minimum_order_amount',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'usage_limit' => 'integer',
            'usage_per_user_limit' => 'integer',
            'used_count' => 'integer',
            'minimum_order_amount' => 'decimal:2',
            'active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get stores that this promo code applies to.
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'promo_code_stores');
    }

    /**
     * Get categories that this promo code applies to.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promo_code_categories');
    }

    /**
     * Get products that this promo code applies to.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promo_code_products');
    }

    /**
     * Get all usages of this promo code.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    /**
     * Check if promo code is valid.
     */
    public function isValid(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = now();
        if ($now->lt($this->valid_from) || $now->gt($this->valid_to)) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for a given order total.
     */
    public function calculateDiscount(float $orderTotal): float
    {
        if ($this->discount_type === 'percentage') {
            return ($orderTotal * $this->discount_value) / 100;
        }

        return min($this->discount_value, $orderTotal);
    }

    /**
     * Scope: Active promo codes.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: Valid promo codes (active and within date range).
     */
    public function scopeValid($query)
    {
        $now = now();
        return $query->where('active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_to', '>=', $now)
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }
}

