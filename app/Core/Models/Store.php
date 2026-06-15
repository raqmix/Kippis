<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\StoreFactory;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return StoreFactory::new();
    }

    protected $fillable = [
        'name',
        'name_localized',
        'address',
        'latitude',
        'longitude',
        'phone',
        'open_time',
        'close_time',
        'is_active',
        'receive_online_orders',
        'is_employee_only',
        'foodics_branch_id',
        'foodics_menu_group_id',
        'synced_from_foodics_at',
        'kiosk_api_key',
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
            'is_employee_only' => 'boolean',
            'synced_from_foodics_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Issue a new kiosk API key. The plaintext is returned for one-time display
     * and only its SHA-256 hash is persisted, so the usable key never lives in
     * the database at rest.
     */
    public function generateKioskApiKey(): string
    {
        $plain = (string) \Illuminate\Support\Str::uuid();
        $this->update(['kiosk_api_key' => hash('sha256', $plain)]);

        return $plain;
    }

    /**
     * Verify a presented kiosk API key against the stored hash in constant time.
     */
    public function verifyKioskApiKey(?string $presented): bool
    {
        if (empty($this->kiosk_api_key) || empty($presented)) {
            return false;
        }

        return hash_equals($this->kiosk_api_key, hash('sha256', $presented));
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
     * Scope: Visible to a given customer. Hides employee-only stores
     * (e.g. Factory) from non-staff customers and guests.
     */
    public function scopeVisibleTo($query, ?Customer $customer)
    {
        if ($customer && $customer->is_staff) {
            return $query;
        }

        return $query->where('is_employee_only', false);
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

    /**
     * Get all orders for this store.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all carts for this store.
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Get promo codes that apply to this store.
     */
    public function promoCodes(): BelongsToMany
    {
        return $this->belongsToMany(PromoCode::class, 'promo_code_stores');
    }

    /**
     * Products available at this branch (per-branch Foodics menu group).
     * A product with no rows in product_store is treated as available
     * everywhere — see Product::scopeAvailableAtStore().
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_store')
            ->withTimestamps();
    }
}

