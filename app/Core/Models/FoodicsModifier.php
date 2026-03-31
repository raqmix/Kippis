<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FoodicsModifier extends Model
{
    protected $fillable = [
        'foodics_id',
        'name_json',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'name_json'      => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getName(string $locale = 'en', ?string $fallback = null): string
    {
        $name = $this->name_json;
        if (is_array($name) && isset($name[$locale])) {
            return $name[$locale];
        }
        return $fallback ?? ($name['en'] ?? '');
    }

    public function options(): HasMany
    {
        return $this->hasMany(FoodicsModifierOption::class, 'foodics_modifier_id')
                    ->orderBy('sort_order');
    }

    public function activeOptions(): HasMany
    {
        return $this->options()->where('is_active', true);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_foodics_modifiers', 'foodics_modifier_id', 'product_id')
            ->withPivot(
                'minimum_options', 'maximum_options', 'free_options',
                'default_option_ids', 'excluded_option_ids',
                'unique_options', 'is_splittable_in_half', 'sort_order'
            )
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
