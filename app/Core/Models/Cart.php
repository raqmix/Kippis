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

    public const DISCOUNT_SOURCE_APP_OFFER = 'app_offer';
    public const DISCOUNT_SOURCE_PROMOTION = 'promotion';

    protected $fillable = [
        'customer_id',
        'session_id',
        'store_id',
        'promo_code_id',
        'wallet_item_id',
        'wallet_discount',
        'points_used',
        'points_discount',
        'applied_promotions_snapshot',
        'platform',
        'app_discount',
        'cup_card_discount',
        'promo_discount',
        'discount_source',
        'app_offer_snapshot',
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
            'applied_promotions_snapshot' => 'array',
            'app_discount'    => 'decimal:2',
            'cup_card_discount' => 'decimal:2',
            'promo_discount'  => 'decimal:2',
            'app_offer_snapshot' => 'array',
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
     *
     * Discount order (app brief, items 1/2/6):
     *   1) reward / wallet gift value comes off first (product reward:
     *      linked product price; free_item reward: most expensive
     *      qualifying line, capped). Capped at the subtotal.
     *   2) app discount = app_offer.percent x (subtotal - gift), only for
     *      carts on an app platform (app_offer.platforms).
     *      The cup card (KIP-36) adds a discount on ONE cup inside this
     *      candidate (cup_card_discount, not part of app_discount).
     *   3) promotions (engine: auto-apply + explicit code), capped at
     *      subtotal - gift. Promotions do NOT combine with the app
     *      discount: the larger of the two wins (tie -> app discount) and
     *      app_offer_snapshot records the loser so the app can say "your
     *      app discount is better than this code".
     *   4) raw points -> EGP via loyalty.points_to_egp_rate, capped at what
     *      is left.
     *
     * For carts without the app discount the totals equal the old
     * promo-then-wallet order (both cap at the subtotal); only the split
     * between promo and wallet can differ when they exceed it.
     */
    public function recalculate(): void
    {
        // Stamp the client platform from the current customer-facing
        // request, unknown included (a header-less web call on an app
        // cart must lose the app discount). Console / queue and gateway
        // returns (3DS) give null and keep what the cart already has.
        $requestPlatform = \App\Support\ClientPlatform::forStamping();
        if ($requestPlatform !== null) {
            $this->platform = $requestPlatform;
        }

        // Ensure product is loaded so we can fall back to base_price for items
        // whose snapshot price is 0 (e.g. added before prices were set).
        //
        // promoCode is force-reloaded: callers such as CartController::sync()
        // load it before mutating promo_code_id, and a plain load() would skip
        // an already-loaded relation and leave us discounting against a stale
        // (or just-detached) promo.
        $this->load(['items.product', 'walletItem.redeemItem.product']);
        $this->loadMissing('promoCode');
        if ($this->relationLoaded('promoCode')
            && $this->promoCode?->getKey() !== $this->promo_code_id) {
            $this->unsetRelation('promoCode')->load('promoCode');
        }

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
        $subtotal = round((float) $subtotal, 2);

        // 1) Reward / wallet gift.
        $walletDiscount = 0.0;
        if ($this->walletItem && $this->walletItem->isUsable()) {
            $value = app(\App\Services\Rewards\RewardValueCalculator::class)
                ->valueFor($this->walletItem, $this);
            $walletDiscount = round(min($value, $subtotal), 2);
        }
        $base = max(0.0, round($subtotal - $walletDiscount, 2));

        // 2) App discount candidate = cup card on one cup + flat % on the
        // rest. The card cup gets the higher of the card and the flat %
        // (plan() only applies the card when it beats the flat %), so the
        // flat % never stacks on it. See CupCardService.
        $appOffer = app(\App\Services\AppOffer\AppOffer::class);
        $appEligible = $appOffer->appliesTo($this->platform);
        $cupPlan = app(\App\Services\CupCard\CupCardService::class)
            ->plan($this, $base, $appEligible ? $appOffer->percent() : 0.0);
        $cardCandidate = $cupPlan['discount'];
        $flatBase = $cupPlan['applied'] ? max(0.0, round($base - $cupPlan['unit_price'], 2)) : $base;
        $flatCandidate = $appEligible ? $appOffer->discountFor($flatBase) : 0.0;
        $appCandidate = round($cardCandidate + $flatCandidate, 2);

        // 3) Promotions candidate. The engine reads cart.promo_code_id
        // internally for the explicit code, so we don't pass it twice.
        $evaluator = app(\App\Services\Promotions\PromotionEvaluator::class);
        $evaluation = $evaluator->evaluate(
            $this,
            $this->customer,
            $this->store,
        );
        $promoCandidate = round((float) min($evaluation->totalDiscount, $base), 2);

        $appDiscount = 0.0;
        $cupCardDiscount = 0.0;
        $promoDiscount = 0.0;
        $source = null;
        $appliedSnapshot = [];
        $suppressed = [];
        if (($appEligible || $cupPlan['applied']) && $appCandidate > 0 && $appCandidate >= $promoCandidate) {
            $source = self::DISCOUNT_SOURCE_APP_OFFER;
            $appDiscount = $flatCandidate;
            $cupCardDiscount = $cardCandidate;
            $suppressed = $evaluation->snapshot();
        } elseif ($promoCandidate > 0) {
            $source = self::DISCOUNT_SOURCE_PROMOTION;
            $promoDiscount = $promoCandidate;
            $appliedSnapshot = $evaluation->snapshot();
        }

        // The card is only used when the app offer won (a winning promotion drops it).
        $cupPlan['applied'] = $cupCardDiscount > 0;
        $cupPlan['discount'] = $cupCardDiscount;

        $remaining = max(0.0, $base - $appDiscount - $cupCardDiscount - $promoDiscount);

        // 4) Raw points.
        $pointsDiscount = 0;
        $pointsUsed = (int) $this->points_used;
        if ($pointsUsed > 0) {
            $rate = (int) \App\Core\Models\Setting::get('loyalty.points_to_egp_rate', 10);
            if ($rate > 0) {
                // Round half-down so 9 points / rate 10 = 0 EGP (no free
                // money on sub-unit conversions).
                $pointsDiscount = floor($pointsUsed / $rate);
                $pointsDiscount = min($pointsDiscount, $remaining);
                // Charge only the points the (possibly capped) discount is
                // worth, rounded down so the customer never pays more
                // points than the value received.
                $pointsUsed = min($pointsUsed, (int) floor(round($pointsDiscount * $rate, 6)));
            } else {
                $pointsUsed = 0;
            }
        }

        $totalDiscount = round($walletDiscount + $appDiscount + $cupCardDiscount + $promoDiscount + $pointsDiscount, 2);

        $this->update([
            'platform'        => $this->platform,
            'subtotal'        => $subtotal,
            'discount'        => $totalDiscount,
            'wallet_discount' => $walletDiscount,
            'app_discount'    => $appDiscount,
            'cup_card_discount' => $cupCardDiscount,
            'promo_discount'  => $promoDiscount,
            'discount_source' => $source,
            'points_discount' => $pointsDiscount,
            'applied_promotions_snapshot' => $appliedSnapshot,
            'app_offer_snapshot' => [
                'eligible'       => $appEligible,
                'percent'        => $appEligible ? $appOffer->percent() : 0,
                'base'           => $base,
                'app_discount'   => $flatCandidate,
                'promo_discount' => $promoCandidate,
                'winner'         => $source,
                'suppressed_promotions' => $suppressed,
                'cup_card'       => $cupPlan,
            ],
            // points_used is what the customer is actually charged: the
            // requested points, reduced when the discount was capped.
            'points_used'     => $pointsUsed,
            'total'           => max(0, round($subtotal - $totalDiscount, 2)),
        ]);
    }
}
