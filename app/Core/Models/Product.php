<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name_json',
        'description_json',
        'image',
        'base_price',
        'is_active',
        'external_source',
        'foodics_id',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'description_json' => 'array',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
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
    public function getName(string $locale = 'en', ?string $fallback = null): string
    {
        $name = $this->name_json;
        
        if (is_array($name) && isset($name[$locale])) {
            return $name[$locale];
        }

        return $fallback ?? ($name['en'] ?? '');
    }

    /**
     * Get localized description for a specific locale.
     *
     * @param string $locale
     * @param string|null $fallback
     * @return string
     */
    public function getDescription(string $locale = 'en', ?string $fallback = null): string
    {
        $description = $this->description_json;
        
        if (is_array($description) && isset($description[$locale])) {
            return $description[$locale];
        }

        return $fallback ?? ($description['en'] ?? '');
    }

    /**
     * Get the category that owns this product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope: Active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Local products.
     */
    public function scopeLocal($query)
    {
        return $query->where('external_source', 'local');
    }

    /**
     * Scope: Foodics products.
     */
    public function scopeFoodics($query)
    {
        return $query->where('external_source', 'foodics');
    }
}

