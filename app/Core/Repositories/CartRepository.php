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
     * - findActiveCart(null, $sessionId, $includeProductDetails, $storeId) - kiosk pattern with store filter
     *
     * @param int|null $customerId The customer ID (null for guest carts)
     * @param bool|string|null $sessionIdOrIncludeProduct The session ID (string) OR includeProductDetails (bool) for backward compatibility
     * @param bool|int|null $includeProductDetailsOrStoreId If bool, treat as includeProductDetails. If int, treat as storeId (when sessionId is provided as string). If null and 4th param is int, use 4th param as storeId
     * @param int|null $storeId Optional store ID to filter by (for kiosk)
     */
    public function findActiveCart(?int $customerId = null, $sessionIdOrIncludeProduct = false, $includeProductDetailsOrStoreId = false, ?int $storeId = null): ?Cart
    {
        $relationships = ['items', 'promoCode', 'store'];
        
        // Handle backward compatibility: if second param is bool, treat it as includeProductDetails
        $sessionId = null;
        $includeProductDetails = false;
        
        if (is_string($sessionIdOrIncludeProduct)) {
            $sessionId = $sessionIdOrIncludeProduct;
            // Third param could be bool (includeProduct) or int (storeId)
            if (is_bool($includeProductDetailsOrStoreId)) {
                $includeProductDetails = $includeProductDetailsOrStoreId;
            } elseif (is_int($includeProductDetailsOrStoreId)) {
                $storeId = $includeProductDetailsOrStoreId;
            }
            // If third param is null and fourth param is provided, use fourth param as storeId
            if ($includeProductDetailsOrStoreId === null && $storeId !== null) {
                // storeId already set from 4th param
            }
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
        
        // Filter by store_id if provided (for kiosk)
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
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
     * If an identical item exists (same product_id, item_type, and configuration), the quantity
     * will be incremented instead of creating a duplicate.
     *
     * $payload keys: item_type, ref_id, name, price, quantity, configuration (array), note (string)
     */
    public function addItemUnified(Cart $cart, array $payload): CartItem
    {
        return \DB::transaction(function () use ($cart, $payload) {
            $productId = $payload['product_id'] ?? null;
            $itemType = $payload['item_type'] ?? 'product';
            $configuration = $payload['configuration'] ?? null;
            $quantity = $payload['quantity'] ?? 1;

            // Check if an identical item already exists in the cart
            $existingItem = $this->findExistingCartItem($cart, $productId, $itemType, $configuration);

            if ($existingItem) {
                // Update quantity of existing item
                $existingItem->quantity += $quantity;
                $existingItem->save();
                return $existingItem;
            }

            // Create new item if no match found
            $data = [
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $payload['price'] ?? 0.0,
                'modifiers_snapshot' => $payload['modifiers_snapshot'] ?? null,
                'item_type' => $itemType,
                'ref_id' => $payload['ref_id'] ?? null,
                'name' => $payload['name'] ?? null,
                'configuration' => $configuration,
                'note' => $payload['note'] ?? null,
            ];

            return CartItem::create($data);
        });
    }

    /**
     * Find an existing cart item that matches the given criteria.
     *
     * Items are considered identical if they have the same:
     * - product_id (for products)
     * - item_type
     * - configuration (including addons)
     *
     * @param Cart $cart
     * @param int|null $productId
     * @param string $itemType
     * @param array|null $configuration
     * @return CartItem|null
     */
    private function findExistingCartItem(Cart $cart, ?int $productId, string $itemType, ?array $configuration): ?CartItem
    {
        $query = $cart->items()
            ->where('item_type', $itemType);

        if ($itemType === 'product' && $productId) {
            $query->where('product_id', $productId);
        }

        $items = $query->get();

        foreach ($items as $item) {
            if ($this->configurationsMatch($item->configuration, $configuration)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Compare two configurations to determine if they are identical.
     *
     * @param mixed $config1
     * @param mixed $config2
     * @return bool
     */
    private function configurationsMatch($config1, $config2): bool
    {
        // Normalize both configurations
        $normalized1 = $this->normalizeConfiguration($config1);
        $normalized2 = $this->normalizeConfiguration($config2);

        // Both null or empty means match
        if (empty($normalized1) && empty($normalized2)) {
            return true;
        }

        // One empty, one not means no match
        if (empty($normalized1) || empty($normalized2)) {
            return false;
        }

        // Compare as JSON for deep equality
        return json_encode($normalized1) === json_encode($normalized2);
    }

    /**
     * Normalize a configuration array for comparison.
     *
     * @param mixed $configuration
     * @return array|null
     */
    private function normalizeConfiguration($configuration): ?array
    {
        if (is_null($configuration)) {
            return null;
        }

        // Handle JSON string
        if (is_string($configuration)) {
            $configuration = json_decode($configuration, true);
        }

        if (!is_array($configuration) || empty($configuration)) {
            return null;
        }

        // Sort addons by modifier_id for consistent comparison
        if (isset($configuration['addons']) && is_array($configuration['addons'])) {
            usort($configuration['addons'], function ($a, $b) {
                return ($a['modifier_id'] ?? 0) <=> ($b['modifier_id'] ?? 0);
            });
        }

        // Sort modifiers by id for consistent comparison (for mix items)
        if (isset($configuration['modifiers']) && is_array($configuration['modifiers'])) {
            usort($configuration['modifiers'], function ($a, $b) {
                return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            });
        }

        // Sort extras for consistent comparison
        if (isset($configuration['extras']) && is_array($configuration['extras'])) {
            sort($configuration['extras']);
        }

        return $configuration;
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

