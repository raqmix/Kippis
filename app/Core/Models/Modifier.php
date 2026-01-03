<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\ModifierFactory;

class Modifier extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ModifierFactory::new();
    }

    protected $fillable = [
        'type',
        'name_json',
        'max_level',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'max_level' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
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
     * Scope: Active modifiers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the products that have this modifier as an addon.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_modifier_groups')
            ->withPivot('is_required', 'min_select', 'max_select')
            ->withTimestamps();
    }
}

