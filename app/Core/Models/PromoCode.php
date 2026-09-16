<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\PromoCodeFactory;

class PromoCode extends Model
{
    use HasFactory, SoftDeletes;

    // Discount types — the original two (percentage / fixed) are
    // back-compat with the existing promo_codes rows; the new types
    // were added in the 2026-06-17 promotions-engine migration.
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_BUY_X_GET_Y = 'buy_x_get_y';
    public const TYPE_FREE_ITEM = 'free_item';
    public const TYPE_FREE_DELIVERY = 'free_delivery';

    public const TYPES = [
        self::TYPE_PERCENTAGE,
        self::TYPE_FIXED,
        self::TYPE_BUY_X_GET_Y,
        self::TYPE_FREE_ITEM,
        self::TYPE_FREE_DELIVERY,
    ];

    // Customer segments — used inside the `conditions.segments` JSON
    // array to scope a promo (e.g. ['new_customer'] = first-order-only
    // surface; ['staff'] = employee perk; absent/empty = everyone).
    public const SEGMENT_EVERYONE = 'everyone';
    public const SEGMENT_NEW_CUSTOMER = 'new_customer';
    public const SEGMENT_LOYAL = 'loyal';
    public const SEGMENT_STAFF = 'staff';

    public const SEGMENTS = [
        self::SEGMENT_EVERYONE,
        self::SEGMENT_NEW_CUSTOMER,
        self::SEGMENT_LOYAL,
        self::SEGMENT_STAFF,
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PromoCodeFactory::new();
    }

    protected $fillable = [
        'code',
        'name_json',
        'description_json',
        'discount_type',
        'discount_value',
        'auto_apply',
        'priority',
        'stackable',
        'config',
        'conditions',
        'valid_from',
        'valid_to',
        'usage_limit',
        'usage_per_user_limit',
        'used_count',
        'minimum_order_amount',
        'active',
        'visible_to_customer',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'description_json' => 'array',
            'discount_value' => 'decimal:2',
            'auto_apply' => 'boolean',
            'priority' => 'integer',
            'stackable' => 'boolean',
            'config' => 'array',
            'conditions' => 'array',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'usage_limit' => 'integer',
            'usage_per_user_limit' => 'integer',
            'used_count' => 'integer',
            'minimum_order_amount' => 'decimal:2',
            'active' => 'boolean',
            'visible_to_customer' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'promo_code_stores');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promo_code_categories');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promo_code_products');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    /**
     * Localized display name. Falls back to the raw code (for legacy
     * promos created before name_json existed) so admin lists never
     * render blank.
     */
    public function getName(string $locale = 'en'): string
    {
        $name = $this->name_json;
        if (is_array($name) && isset($name[$locale]) && $name[$locale] !== '') {
            return $name[$locale];
        }
        if (is_array($name) && isset($name['en']) && $name['en'] !== '') {
            return $name['en'];
        }
        return $this->code ?? '';
    }

    public function getDescription(string $locale = 'en'): string
    {
        $desc = $this->description_json;
        if (is_array($desc) && isset($desc[$locale]) && $desc[$locale] !== '') {
            return $desc[$locale];
        }
        return is_array($desc) && isset($desc['en']) ? $desc['en'] : '';
    }

    /**
     * Read a key from the type-specific `config` JSON. Used by the
     * calculator classes so they don't all reimplement the null/array
     * guards.
     */
    public function configValue(string $key, $default = null)
    {
        $cfg = $this->config;
        if (!is_array($cfg)) {
            return $default;
        }
        return $cfg[$key] ?? $default;
    }

    /**
     * Read a key from the generic `conditions` JSON.
     */
    public function conditionValue(string $key, $default = null)
    {
        $cond = $this->conditions;
        if (!is_array($cond)) {
            return $default;
        }
        return $cond[$key] ?? $default;
    }

    /**
     * True when this promo is checked automatically against every
     * cart recalc (no code field needed).
     */
    public function isAutoApply(): bool
    {
        return (bool) $this->auto_apply;
    }

    public function isValid(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = now();
        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_to && $now->gt($this->valid_to)) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Back-compat helper for the legacy `Cart::recalculate()` path.
     * Only meaningful for percentage / fixed types — the new types
     * compute their own amounts via the calculator classes and never
     * go through this method.
     */
    public function calculateDiscount(float $orderTotal): float
    {
        if ($this->discount_type === self::TYPE_PERCENTAGE) {
            return ($orderTotal * (float) $this->discount_value) / 100;
        }

        if ($this->discount_type === self::TYPE_FIXED) {
            return min((float) $this->discount_value, $orderTotal);
        }

        return 0.0;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Active + within the valid_from / valid_to window + below usage cap.
     */
    public function scopeValid($query)
    {
        $now = now();
        return $query->where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    /**
     * Only promos that the engine should evaluate on every recalc
     * (no code typed). Ordered by priority desc so the loop in
     * PromotionEvaluator picks the highest-priority winners first.
     */
    public function scopeAutoApply($query)
    {
        return $query->where('auto_apply', true)
            ->orderByDesc('priority')
            ->orderByDesc('id');
    }

    /**
     * Promos ops has explicitly opted in to show on the customer-facing
     * Offers surface. Composes with scopeValid(): use ->valid()->visible()
     * to get "active + in-window + browsable" for the /v1/offers endpoint.
     */
    public function scopeVisibleToCustomer($query)
    {
        return $query->where('visible_to_customer', true);
    }
}
