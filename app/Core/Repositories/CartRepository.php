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
     */
    public function findActiveCart(?int $customerId = null, ?string $sessionId = null): ?Cart
    {
        $query = Cart::with(['items.product', 'promoCode', 'store'])
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
     */
    public function addItem(Cart $cart, int $productId, int $quantity, array $modifiers = []): CartItem
    {
        $product = Product::findOrFail($productId);

        return CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $product->base_price,
            'modifiers_snapshot' => $modifiers,
        ]);
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
        return $cart->update(['promo_code_id' => $promoCode->id]);
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

