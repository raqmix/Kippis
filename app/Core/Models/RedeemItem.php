<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RedeemItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'title_json',
        'description_json',
        'image',
        'points_cost',
        'max_per_customer_lifetime',
        'max_per_customer_per_day',
        'max_global',
        'wallet_ttl_days',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'title_json'                => 'array',
            'description_json'          => 'array',
            'points_cost'               => 'integer',
            'max_per_customer_lifetime' => 'integer',
            'max_per_customer_per_day'  => 'integer',
            'max_global'                => 'integer',
            'wallet_ttl_days'           => 'integer',
            'is_active'                 => 'boolean',
            'sort_order'                => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'redeem_item_store');
    }

    public function walletEntries(): HasMany
    {
        return $this->hasMany(CustomerRedeemWallet::class);
    }

    public function getTitle(string $locale = 'en'): string
    {
        $json = $this->title_json ?? [];
        return (string) ($json[$locale] ?? $json['en'] ?? $json['ar'] ?? '');
    }

    public function getDescription(string $locale = 'en'): ?string
    {
        $json = $this->description_json ?? [];
        return $json[$locale] ?? $json['en'] ?? $json['ar'] ?? null;
    }

    /** Active and (when $storeId given) available at that branch. */
    public function scopeAvailableAt(Builder $query, ?int $storeId): Builder
    {
        $query->where('is_active', true);

        if ($storeId === null) {
            return $query;
        }

        // Match product_store semantics: zero pivot rows means
        // available everywhere; one+ rows means scoped to those branches.
        return $query->where(function (Builder $q) use ($storeId) {
            $q->whereDoesntHave('stores')
              ->orWhereHas('stores', fn (Builder $s) => $s->where('stores.id', $storeId));
        });
    }
}
