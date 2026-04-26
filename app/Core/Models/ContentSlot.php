<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContentSlot extends Model
{
    protected $fillable = [
        'slot_key',
        'title_en',
        'title_ar',
        'subtitle_en',
        'subtitle_ar',
        'image',
        'cta_text_en',
        'cta_text_ar',
        'cta_action',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
        'platform',
    ];

    protected function casts(): array
    {
        return [
            'cta_action' => 'array',
            'platform'   => 'array',
            'is_active'  => 'boolean',
            'starts_at'  => 'datetime',
            'ends_at'    => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where(function ($q) use ($platform) {
            $q->whereNull('platform')
              ->orWhereJsonContains('platform', $platform);
        });
    }
}
