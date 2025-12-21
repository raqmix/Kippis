<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'name_localized',
        'address',
        'latitude',
        'longitude',
        'open_time',
        'close_time',
        'is_active',
        'receive_online_orders',
        'foodics_branch_id',
        'synced_from_foodics_at',
    ];

    protected function casts(): array
    {
        return [
            'name_localized' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'open_time' => 'string',
            'close_time' => 'string',
            'is_active' => 'boolean',
            'receive_online_orders' => 'boolean',
            'synced_from_foodics_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get localized name for a specific locale.
     *
     * @param string $locale
     * @param string|null $fallback
     * @return string
     */
    public function getNameLocalized(string $locale = 'en', ?string $fallback = null): string
    {
        $localized = $this->name_localized;
        
        if (is_array($localized) && isset($localized[$locale])) {
            return $localized[$locale];
        }

        return $fallback ?? $this->name;
    }

    /**
     * Check if store is open now.
     *
     * @return bool
     */
    public function isOpenNow(): bool
    {
        if (!$this->open_time || !$this->close_time) {
            return true; // If no hours set, assume always open
        }

        $now = now();
        $openTime = $now->copy()->setTimeFromTimeString($this->open_time);
        $closeTime = $now->copy()->setTimeFromTimeString($this->close_time);

        // Handle overnight hours (e.g., 22:00 - 02:00)
        if ($closeTime->lessThan($openTime)) {
            return $now->greaterThanOrEqualTo($openTime) || $now->lessThan($closeTime);
        }

        return $now->greaterThanOrEqualTo($openTime) && $now->lessThan($closeTime);
    }

    /**
     * Scope: Active stores that receive online orders.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActiveForOrders($query)
    {
        return $query->where('is_active', true)
            ->where('receive_online_orders', true);
    }

    /**
     * Scope: Has Foodics mapping.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasFoodicsMapping($query)
    {
        return $query->whereNotNull('foodics_branch_id');
    }
}

