<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodicsModifierOption extends Model
{
    protected $fillable = [
        'foodics_id',
        'foodics_modifier_group_id',
        'name_json',
        'price',
        'is_active',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'name_json'      => 'array',
            'price'          => 'decimal:2',
            'is_active'      => 'boolean',
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

    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(FoodicsModifierGroup::class, 'foodics_modifier_group_id');
    }
}
