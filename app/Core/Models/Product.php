<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Database\Factories\ProductFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    protected $appends = ['image_url'];

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
        'product_kind',
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
     * Returns a fully-qualified public URL for the image regardless of source.
     * Foodics products have a full URL; local products have a relative storage path.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return Storage::url($this->image);
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

    /**
     * Get the modifier groups assigned to this product (addons).
     */
    public function modifierGroups()
    {
        return $this->hasMany(ProductModifierGroup::class);
    }

    /**
     * Get the Foodics modifier groups (option groups) synced from Foodics.
     */
    public function foodicsModifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(FoodicsModifierGroup::class, 'product_foodics_modifier_groups')
            ->withPivot('minimum_options', 'maximum_options', 'free_options', 'index')
            ->withTimestamps()
            ->orderByPivot('index');
    }

    /**
     * Get the modifiers assigned to this product as addons.
     */
    public function addonModifiers()
    {
        return $this->belongsToMany(Modifier::class, 'product_modifier_groups')
            ->withPivot('is_required', 'min_select', 'max_select')
            ->withTimestamps();
    }

    /**
     * Scope: Mix base products.
     */
    public function scopeMixBases($query)
    {
        return $query->where('product_kind', 'mix_base');
    }

    /**
     * Scope: Regular products (not mix bases).
     */
    public function scopeRegular($query)
    {
        return $query->where('product_kind', 'regular');
    }

    /**
     * Get the mix builder bases pivot entries for this product.
     */
    public function mixBuilderBases()
    {
        return $this->hasMany(MixBuilderBase::class);
    }
}

