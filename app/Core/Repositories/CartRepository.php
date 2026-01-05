<?php

namespace App\Core\Repositories;

use App\Core\Models\Cart;
use App\Core\Models\CartItem;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use Illuminate\Database\Eloquent\Collection;

class CartRepository
{
    /**
     * Create a new cart.
     */
    public function create(array $data): Cart
    {
        return Cart::create($data);
    }

    /**
     * Find active cart for customer or session.
     *
     * Supports multiple calling patterns for backward compatibility:
     * - findActiveCart($customerId, $includeProductDetails) - old pattern for authenticated users
     * - findActiveCart($customerId, $sessionId, $includeProductDetails) - new pattern for both
     * - findActiveCart(null, $sessionId, $includeProductDetails) - new pattern for guest carts
     *
     * @param int|null $customerId The customer ID (null for guest carts)
     * @param bool|string|null $sessionIdOrIncludeProduct The session ID (string) OR includeProductDetails (bool) for backward compatibility
     * @param bool $includeProductDetails If true, loads product with addonModifiers and category
     */
    public function findActiveCart(?int $customerId = null, $sessionIdOrIncludeProduct = false, bool $includeProductDetails = false): ?Cart
    {
        $relationships = ['items', 'promoCode', 'store'];
        
        // Handle backward compatibility: if second param is bool, treat it as includeProductDetails
        $sessionId = null;
        if (is_string($sessionIdOrIncludeProduct)) {
            $sessionId = $sessionIdOrIncludeProduct;
        } elseif (is_bool($sessionIdOrIncludeProduct)) {
            $includeProductDetails = $sessionIdOrIncludeProduct;
        }
        
        if ($includeProductDetails) {
            $relationships[] = 'items.product.addonModifiers';
            $relationships[] = 'items.product.category';
        } else {
            $relationships[] = 'items.product';
        }
        
        $query = Cart::with($relationships)
            ->whereNull('abandoned_at');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return null;
        }

        return $query->latest()->first();
    }

    /**
     * Add item to cart.
     *
     * @param string|null $note Optional note for the cart item
     */
    public function addItem(Cart $cart, int $productId, int $quantity, array $modifiers = [], ?string $note = null): CartItem
    {
        $product = Product::findOrFail($productId);

        $payload = [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => (float) $product->base_price,
            'modifiers_snapshot' => $modifiers,
            'item_type' => 'product',
            'ref_id' => $product->id,
            'name' => $product->name_json['en'] ?? $product->name,
            'configuration' => null,
            'note' => $note,
        ];

        return $this->addItemUnified($cart, $payload);
    }

    /**
     * Unified add item API. Accepts payload with item_type and configuration. Price must be provided
     * or computed by caller (e.g., MixPriceCalculator) before calling this method.
     *
     * $payload keys: item_type, ref_id, name, price, quantity, configuration (array), note (string)
     */
    public function addItemUnified(Cart $cart, array $payload): CartItem
    {
        return \DB::transaction(function () use ($cart, $payload) {
            $data = [
                'cart_id' => $cart->id,
                'product_id' => $payload['product_id'] ?? null,
                'quantity' => $payload['quantity'] ?? 1,
                'price' => $payload['price'] ?? 0.0,
                'modifiers_snapshot' => $payload['modifiers_snapshot'] ?? null,
                'item_type' => $payload['item_type'] ?? 'product',
                'ref_id' => $payload['ref_id'] ?? null,
                'name' => $payload['name'] ?? null,
                'configuration' => $payload['configuration'] ?? null,
                'note' => $payload['note'] ?? null,
            ];

            return CartItem::create($data);
        });
    }

    /**
     * Update cart item.
     */
    public function updateItem(CartItem $cartItem, array $data): bool
    {
        return $cartItem->update($data);
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(CartItem $cartItem): bool
    {
        return $cartItem->delete();
    }

    /**
     * Apply promo code to cart.
     */
    public function applyPromoCode(Cart $cart, PromoCode $promoCode): bool
    {
        $result = $cart->update(['promo_code_id' => $promoCode->id]);
        
        // Refresh the cart to ensure the promo_code_id is loaded
        $cart->refresh();
        
        return $result;
    }

    /**
     * Remove promo code from cart.
     */
    public function removePromoCode(Cart $cart): bool
    {
        return $cart->update(['promo_code_id' => null]);
    }

    /**
     * Abandon cart.
     */
    public function abandon(Cart $cart): bool
    {
        return $cart->update(['abandoned_at' => now()]);
    }

    /**
     * Recalculate cart totals.
     */
    public function recalculate(Cart $cart): void
    {
        // Ensure items and promoCode relationships are loaded
        if (!$cart->relationLoaded('items')) {
            $cart->load('items');
        }
        if (!$cart->relationLoaded('promoCode')) {
            $cart->load('promoCode');
        }

        $cart->recalculate();
    }
}

