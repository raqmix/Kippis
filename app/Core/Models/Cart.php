<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'session_id',
        'store_id',
        'promo_code_id',
        'subtotal',
        'discount',
        'total',
        'abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'abandoned_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that owns this cart (if authenticated).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the store for this cart.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the promo code applied to this cart.
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * Get all items in this cart.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Recalculate cart totals.
     */
    public function recalculate(): void
    {
        $subtotal = $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        $discount = 0;
        if ($this->promoCode && $this->promoCode->isValid() && $subtotal >= $this->promoCode->minimum_order_amount) {
            $discount = $this->promoCode->calculateDiscount($subtotal);
        }

        $this->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
        ]);
    }
}

