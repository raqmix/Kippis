<?php

namespace App\Core\Repositories;

use App\Core\Models\FoodicsModifierOption;
use App\Core\Models\Order;
use App\Core\Models\PaymentMethod;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use App\Core\Models\PromoCodeUsage;
use App\Events\OrderCreated;
use App\Services\MixPriceCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    public function __construct(
        private MixPriceCalculator $mixPriceCalculator
    ) {
    }

    /**
     * Create order from cart.
     *
     * @param \App\Core\Models\Cart $cart
     * @param int $paymentMethodId Payment method ID from payment_methods table
     * @param int|null $storeId Optional store ID. If provided, overrides the cart's store_id.
     * @return Order
     */
    public function createFromCart(
        \App\Core\Models\Cart $cart,
        int $paymentMethodId,
        ?int $storeId = null,
        ?string $gatewayOrderId = null
    ): Order
    {
        $pickupCode = $this->generatePickupCode();

        // Get payment method details
        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);

        // Atomic: Order + PromoCodeUsage + promo.used_count increment all
        // succeed or none do (#17). Lock the promo row so two parallel
        // checkouts can't blow past `usage_limit` between the read and
        // the increment (#24). Discount is recomputed against the locked
        // row's discount_value/discount_type so a mid-flight admin edit
        // is honoured atomically.
        return DB::transaction(function () use (
            $cart,
            $paymentMethodId,
            $storeId,
            $gatewayOrderId,
            $paymentMethod,
            $pickupCode
        ) {
            $promoCode = null;
            $discount = (float) $cart->discount;
            $promoCodeId = $cart->promo_code_id;

            if ($cart->promo_code_id) {
                $promoCode = PromoCode::query()
                    ->whereKey($cart->promo_code_id)
                    ->lockForUpdate()
                    ->first();

                if ($promoCode && $promoCode->isValid()) {
                    // Recompute the discount from the locked row in case
                    // admin edited discount_value while the cart sat.
                    $discount = round(min(
                        $promoCode->calculateDiscount((float) $cart->subtotal),
                        (float) $cart->subtotal
                    ), 2);
                } else {
                    // Promo expired or exhausted between cart load and
                    // checkout — drop it silently. (For card orders the
                    // gateway charge already used cart.total at pay()
                    // time; we honour what the customer was charged by
                    // keeping the existing discount but null the promo
                    // refs so used_count doesn't get bumped against a
                    // dead code.)
                    $promoCode = null;
                    $promoCodeId = null;
                }
            }

            // Loyalty-as-discount snapshots from the cart. The cart's
            // recalculate() already capped these against the
            // subtotal-after-promo, so they can never make the order
            // total negative. We re-clamp here defensively in case of
            // mid-flight changes (e.g. an admin disabled redemption).
            $walletItem = null;
            $walletDiscount = 0.0;
            $pointsUsed = 0;
            $pointsDiscount = 0.0;
            if ($cart->wallet_item_id) {
                $walletItem = \App\Core\Models\CustomerRedeemWallet::query()
                    ->whereKey($cart->wallet_item_id)
                    ->lockForUpdate()
                    ->first();
                if ($walletItem && $walletItem->isUsable() && $walletItem->customer_id === $cart->customer_id) {
                    $walletDiscount = (float) $cart->wallet_discount;
                } else {
                    $walletItem = null;
                }
            }
            if ((int) $cart->points_used > 0
                && (bool) \App\Core\Models\Setting::get('loyalty.redemption_enabled', true)
            ) {
                $pointsUsed = (int) $cart->points_used;
                $pointsDiscount = (float) $cart->points_discount;
            }

            $allDiscounts = round($discount + $walletDiscount + $pointsDiscount, 2);
            $finalTotal = round(max(0, ((float) $cart->subtotal) - $allDiscounts), 2);

            $order = Order::create([
                'store_id' => $storeId ?? $cart->store_id,
                'customer_id' => $cart->customer_id,
                'status' => 'received',
                'total' => $finalTotal,
                'subtotal' => $cart->subtotal,
                'discount' => $allDiscounts,
                'payment_method' => $paymentMethod->code, // Keep for backward compatibility
                'payment_method_id' => $paymentMethodId,
                // Persist the MPGS gateway order id so RefundService::void()
                // and ::issueRefund() can actually call the gateway. Without
                // this, the refund gate (`if ($order->gateway_order_id)`)
                // silently no-ops and the customer is "refunded" in the DB
                // only — guaranteed chargebacks within weeks.
                'gateway_order_id' => $gatewayOrderId,
                'pickup_code' => $pickupCode,
                'items_snapshot' => $cart->items->map(function ($item) {
                    $img = $item->product?->image;
                    $imageUrl = $img ? (str_starts_with($img, 'http') ? $img : asset('storage/' . $img)) : null;
                    return [
                        'product_id' => $item->product_id,
                        'item_type' => $item->item_type ?? 'product',
                        'name' => $item->name ?? ($item->product ? $item->product->getName(app()->getLocale()) : 'Unknown'),
                        'image' => $imageUrl,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'modifiers' => $item->modifiers_snapshot,
                        'configuration' => $item->configuration,
                    ];
                })->toArray(),
                'modifiers_snapshot' => $cart->items->pluck('modifiers_snapshot')->toArray(),
                'promo_code_id' => $promoCodeId,
                'promo_discount' => $discount,
                'wallet_item_id' => $walletItem?->id,
                'wallet_discount' => $walletDiscount,
                'points_used' => $pointsUsed,
                'points_discount' => $pointsDiscount,
            ]);

            // Mark the wallet item as applied so it doesn't show up in
            // the customer's wallet anymore + so any future refund can
            // re-credit accurately by walking the order → wallet item.
            if ($walletItem) {
                $walletItem->update([
                    'status'        => \App\Core\Models\CustomerRedeemWallet::STATUS_APPLIED,
                    'used_order_id' => $order->id,
                    'used_at'       => now(),
                ]);
            }

            // Deduct raw points off the loyalty wallet — only at order
            // creation, not at apply-points-discount, so an abandoned
            // cart never burns points. deductPoints throws on insufficient
            // balance which atomically aborts the transaction.
            if ($pointsUsed > 0 && $cart->customer_id) {
                $loyaltyRepo = app(\App\Core\Repositories\LoyaltyWalletRepository::class);
                $loyaltyWallet = $loyaltyRepo->getOrCreateForCustomer($cart->customer_id);
                $loyaltyRepo->deductPoints(
                    $loyaltyWallet,
                    $pointsUsed,
                    'redeemed',
                    'Points used as discount on order #' . $order->id,
                    'order_points_discount',
                    $order->id,
                );
            }

            if ($promoCode) {
                if ($cart->customer_id) {
                    PromoCodeUsage::create([
                        'promo_code_id' => $promoCode->id,
                        'customer_id' => $cart->customer_id,
                        'order_id' => $order->id,
                        'discount_amount' => $discount,
                        'used_at' => now(),
                    ]);
                }
                // Guarded UPDATE: only increment when we're still under
                // the usage_limit, so the race between two checkouts
                // grabbing the last redemption slot is decided by the
                // DB rather than by app code (#24).
                PromoCode::query()
                    ->whereKey($promoCode->id)
                    ->where(function ($q) {
                        $q->whereNull('usage_limit')
                          ->orWhereColumn('used_count', '<', 'usage_limit');
                    })
                    ->increment('used_count');
            }

            // Dispatch event for real-time notification — afterCommit so
            // listeners (Foodics push, etc.) only see committed state.
            DB::afterCommit(fn () => event(new OrderCreated($order)));

            return $order;
        });
    }

    /**
     * Create order directly from items array (for kiosk local cart).
     *
     * Prices are recomputed server-side from the catalog; client-supplied
     * per-item prices and totals are never trusted. The subtotal is the sum of
     * recomputed line prices, the discount is derived from the validated promo
     * code, and the total is subtotal minus discount.
     *
     * @param int $storeId
     * @param array $items Array of items with: product_id, item_type, name, quantity, modifiers, configuration, note
     * @param string $paymentMethodCode Payment method code (cash, card, online)
     * @param string|null $promoCode Optional promo code to apply
     * @param string|null $posCode Optional 4-digit POS code for cash payments
     * @return Order
     */
    public function createFromItems(
        int $storeId,
        array $items,
        string $paymentMethodCode,
        ?string $promoCode = null,
        ?string $posCode = null
    ): Order {
        $paymentMethod = PaymentMethod::where('code', $paymentMethodCode)->first();
        if (!$paymentMethod) {
            throw new \InvalidArgumentException("Invalid payment method: {$paymentMethodCode}");
        }

        // Recompute every line price from the catalog — never trust client prices.
        $subtotal = 0.0;
        $snapshotItems = [];
        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = $this->computeLineUnitPrice($item);
            $subtotal += $unitPrice * $quantity;

            $snapshotItems[] = [
                'product_id' => $item['product_id'] ?? null,
                'item_type' => $item['item_type'] ?? 'product',
                'name' => $item['name'] ?? 'Unknown',
                'image' => $item['image'] ?? null,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'modifiers' => $item['modifiers'] ?? null,
                'configuration' => $item['configuration'] ?? null,
                'note' => $item['note'] ?? null,
            ];
        }
        $subtotal = round($subtotal, 2);

        // Resolve the promo and recompute its discount against the server subtotal.
        $discount = 0.0;
        $promoCodeModel = null;
        if ($promoCode) {
            $promoCodeModel = PromoCode::where('code', strtoupper($promoCode))
                ->valid()
                ->first();

            if ($promoCodeModel) {
                if ($subtotal < (float) $promoCodeModel->minimum_order_amount) {
                    throw new \InvalidArgumentException("Minimum order amount not met for promo code");
                }
                $discount = round(min($promoCodeModel->calculateDiscount($subtotal), $subtotal), 2);
            }
        }

        $total = round($subtotal - $discount, 2);

        // For cash payments with POS code, set status to pending_payment
        // Order will be updated to 'received' when cashier confirms payment
        $status = ($paymentMethodCode === 'cash' && $posCode) ? 'pending_payment' : 'received';

        $pickupCode = $this->generatePickupCode();

        return DB::transaction(function () use (
            $storeId,
            $status,
            $total,
            $subtotal,
            $discount,
            $paymentMethodCode,
            $paymentMethod,
            $posCode,
            $pickupCode,
            $snapshotItems,
            $items,
            $promoCodeModel
        ) {
            $order = Order::create([
                'store_id' => $storeId,
                'customer_id' => null, // Guest order for kiosk
                'status' => $status,
                'total' => $total,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'payment_method' => $paymentMethodCode, // Keep for backward compatibility
                'payment_method_id' => $paymentMethod->id,
                'pos_code' => $posCode, // 4-digit code for cash POS processing
                'pickup_code' => $pickupCode,
                'items_snapshot' => $snapshotItems,
                'modifiers_snapshot' => array_map(function ($item) {
                    return $item['modifiers'] ?? null;
                }, $items),
                'promo_code_id' => $promoCodeModel?->id,
                'promo_discount' => $discount,
            ]);

            // Increment promo code usage count (for guest orders, don't track customer usage)
            if ($promoCodeModel) {
                $promoCodeModel->increment('used_count');
            }

            // Dispatch event for real-time notification
            event(new OrderCreated($order));

            return $order;
        });
    }

    /**
     * Recompute the authoritative unit price for a single kiosk line item from
     * the catalog, mirroring the app-side CartService pricing. Throws when the
     * referenced product/configuration is invalid so a tampered or malformed
     * cart fails rather than creating a mispriced order.
     *
     * @throws \InvalidArgumentException
     */
    private function computeLineUnitPrice(array $item): float
    {
        $type = $item['item_type'] ?? 'product';
        $config = $item['configuration'] ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        if ($type === 'product') {
            $product = isset($item['product_id']) ? Product::find($item['product_id']) : null;
            if (!$product || !$product->is_active) {
                throw new \InvalidArgumentException('Product not found or inactive.');
            }

            $addons = $config['addons'] ?? $item['modifiers'] ?? [];
            if (!is_array($addons)) {
                $addons = [];
            }

            $result = $this->mixPriceCalculator->calculateProductWithAddons($product, $addons);

            return round($result['total'] + $this->foodicsOptionsTotal($config['foodics_option_ids'] ?? []), 2);
        }

        // Foodics-native build-your-mix: configured product base + option prices.
        if (!empty($config['foodics_option_ids'])) {
            $mixProductId = config('mix.foodics_product_id');
            $product = $mixProductId ? Product::find($mixProductId) : null;
            if (!$product || !$product->is_active) {
                throw new \InvalidArgumentException('Build Your Mix product is not configured or inactive.');
            }

            return round((float) $product->base_price + $this->foodicsOptionsTotal($config['foodics_option_ids']), 2);
        }

        // Legacy / non-Foodics mix configuration.
        $result = $this->mixPriceCalculator->calculate($config);

        return round($result['total'], 2);
    }

    /**
     * Sum the active Foodics modifier option prices for the given option ids.
     */
    private function foodicsOptionsTotal($optionIds): float
    {
        $ids = array_values(array_unique(array_map('intval', (array) $optionIds)));
        if (empty($ids)) {
            return 0.0;
        }

        return (float) FoodicsModifierOption::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->sum('price');
    }

    /**
     * Get paginated orders for customer.
     */
    public function getPaginatedForCustomer(int $customerId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::where('customer_id', $customerId)
            ->with(['store', 'promoCode']);

        // Filter by status (default to 'active' if not provided)
        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'past') {
            $query->past();
        } elseif (in_array($status, ['received', 'mixing', 'ready', 'completed', 'cancelled'])) {
            $query->where('status', $status);
        }

        // Filter by payment method
        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Filter by store
        if (isset($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        // Date range filters
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Total range filters
        if (isset($filters['total_min'])) {
            $query->where('total', '>=', $filters['total_min']);
        }
        if (isset($filters['total_max'])) {
            $query->where('total', '<=', $filters['total_max']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validate sort_by
        $allowedSorts = ['created_at', 'total', 'status', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        // Validate sort_order must be 'asc' or 'desc'
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Find order by ID for customer.
     */
    public function findByIdForCustomer(int $id, int $customerId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->with(['store', 'promoCode'])
            ->find($id);
    }

    /**
     * Find active order for customer (most recent active order).
     */
    public function findActiveOrderForCustomer(int $customerId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->active()
            ->with(['store', 'promoCode', 'paymentMethod'])
            ->latest()
            ->first();
    }

    /**
     * Update order status.
     */
    public function updateStatus(Order $order, string $status): bool
    {
        return $order->update(['status' => $status]);
    }

    /**
     * Generate a unique 4-digit pickup code.
     *
     * @return string
     */
    private function generatePickupCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::where('pickup_code', $code)->whereIn('status', ['received', 'mixing', 'ready'])->exists());

        return $code;
    }
}

