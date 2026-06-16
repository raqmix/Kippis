<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\CartFactory;

class Cart extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return CartFactory::new();
    }

    protected $fillable = [
        'customer_id',
        'session_id',
        'store_id',
        'promo_code_id',
        'wallet_item_id',
        'wallet_discount',
        'points_used',
        'points_discount',
        'subtotal',
        'discount',
        'total',
        'abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'        => 'decimal:2',
            'discount'        => 'decimal:2',
            'wallet_discount' => 'decimal:2',
            'points_discount' => 'decimal:2',
            'points_used'     => 'integer',
            'total'           => 'decimal:2',
            'abandoned_at'    => 'datetime',
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

    public function walletItem(): BelongsTo
    {
        return $this->belongsTo(CustomerRedeemWallet::class, 'wallet_item_id');
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
        // Ensure product is loaded so we can fall back to base_price for items
        // whose snapshot price is 0 (e.g. added before prices were set).
        $this->load(['items.product', 'walletItem.redeemItem.product']);

        $subtotal = $this->items->sum(function ($item) {
            $price = (float) $item->price;
            if ($price === 0.0 && $item->product) {
                $price = (float) $item->product->base_price;
            }
            // Also persist the corrected price so future recalculations are accurate.
            if ($price !== (float) $item->price) {
                $item->update(['price' => $price]);
            }
            return $price * $item->quantity;
        });

        // Discount stack:
        //   1) promo (existing)
        //   2) wallet item — subsidises the linked product's base price up
        //      to the subtotal-after-promo, so a wallet item never goes
        //      negative or pays the customer money back
        //   3) raw points — converted from points to EGP via
        //      Setting::get('loyalty.points_to_egp_rate', 10), capped at
        //      the remaining cart total
        $promoDiscount = 0;
        if ($this->promoCode && $this->promoCode->isValid() && $subtotal >= $this->promoCode->minimum_order_amount) {
            $promoDiscount = $this->promoCode->calculateDiscount($subtotal);
        }

        $remaining = max(0, $subtotal - $promoDiscount);

        $walletDiscount = 0;
        if ($this->walletItem && $this->walletItem->isUsable()) {
            $linkedProduct = $this->walletItem->redeemItem?->product;
            // No linked product → no implicit discount; an admin claim
            // for a generic coupon will require future logic. For now,
            // skip the wallet credit silently rather than block checkout.
            if ($linkedProduct) {
                $walletDiscount = min((float) $linkedProduct->base_price, $remaining);
            }
        }

        $remaining = max(0, $remaining - $walletDiscount);

        $pointsDiscount = 0;
        $pointsUsed = (int) $this->points_used;
        if ($pointsUsed > 0) {
            $rate = (int) \App\Core\Models\Setting::get('loyalty.points_to_egp_rate', 10);
            if ($rate > 0) {
                // Round half-down so 9 points / rate 10 = 0 EGP (no free
                // money on sub-unit conversions).
                $pointsDiscount = floor($pointsUsed / $rate);
                $pointsDiscount = min($pointsDiscount, $remaining);
            }
        }

        $totalDiscount = $promoDiscount + $walletDiscount + $pointsDiscount;

        $this->update([
            'subtotal'        => $subtotal,
            'discount'        => $totalDiscount,
            'wallet_discount' => $walletDiscount,
            'points_discount' => $pointsDiscount,
            // points_used is admin-supplied — preserve the original
            // request so the customer's "I spent 50 pts" intent doesn't
            // get rewritten by silent recalc rounding.
            'total'           => max(0, $subtotal - $totalDiscount),
        ]);
    }
}

