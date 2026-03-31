<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FoodicsModifierGroup extends Model
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
        return $this->hasMany(FoodicsModifierOption::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_foodics_modifier_groups')
            ->withPivot('minimum_options', 'maximum_options', 'free_options', 'index')
            ->withTimestamps();
    }
}
