<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creator extends Model
{
    protected $fillable = [
        'name_en', 'name_ar', 'bio_en', 'bio_ar',
        'avatar', 'social_handle', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function drops(): HasMany
    {
        return $this->hasMany(CreatorDrop::class);
    }
}
