<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpendRewardTier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'threshold_piasters',
        'cycle',
        'choice_groups',
        'voucher_ttl_days',
        'starts_at',
        'ends_at',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'threshold_piasters' => 'integer',
            'choice_groups' => 'array',
            'voucher_ttl_days' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function issuedRewards()
    {
        return $this->hasMany(CustomerSpendReward::class, 'spend_reward_tier_id');
    }

    /**
     * Only tiers currently in-window and toggled on. Composes with
     * cycle checks in SpendRewardService.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();
        return $query->where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Human-readable name in the app's locale, with EN fallback.
     */
    public function displayName(string $locale = 'en'): string
    {
        return $locale === 'ar'
            ? ($this->name_ar ?: $this->name_en)
            : ($this->name_en ?: $this->name_ar);
    }
}
