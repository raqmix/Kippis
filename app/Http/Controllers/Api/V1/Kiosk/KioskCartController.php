<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\ProductRepository;
use App\Core\Repositories\PromoCodeRepository;
use App\Services\CartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddProductToCartRequest;
use App\Http\Requests\Api\V1\AddMixToCartRequest;
use App\Http\Resources\Api\V1\CartResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Kiosk Cart APIs
 */
class KioskCartController extends Controller
{
    public function __construct(
        private CartRepository $cartRepository,
        private ProductRepository $productRepository,
        private PromoCodeRepository $promoCodeRepository,
        private CartService $cartService
    ) {
    }

    /**
     * Get relationships array based on include_product parameter.
     */
    private function getCartRelationships(bool $includeProduct = false): array
    {
        $relationships = ['items', 'promoCode'];
        
        if ($includeProduct) {
            $relationships[] = 'items.product.addonModifiers';
            $relationships[] = 'items.product.category';
        } else {
            $relationships[] = 'items.product';
        }
        
        return $relationships;
    }

    /**
     * Get or create active cart for session and store.
     */
    private function getOrCreateCart(string $sessionId, int $storeId)
    {
        // Try to find existing cart for this session AND store
        $cart = $this->cartRepository->findActiveCart(null, $sessionId, null, $storeId);
        
        if ($cart) {
            return $cart;
        }

        // Create new session-based cart for this store
        $cart = $this->cartRepository->create([
            'customer_id' => null,
            'session_id' => $sessionId,
            'store_id' => $storeId,
        ]);

        return $cart;
    }

    /**
     * Initialize a new cart for kiosk
     *
     * @response 201 {
     *   "success": true,
     *   "message": "cart_initialized",
     *   "data": {
     *     "cart_id": 123,
     *     "session_id": "abc123xyz"
     *   }
     * }
     */
    public function init(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();

        $cart = $this->cartRepository->create([
            'customer_id' => null,
            'session_id' => $sessionId,
            'store_id' => $store->id,
        ]);

        return apiSuccess([
            'cart_id' => $cart->id,
            'session_id' => $sessionId,
        ], 'cart_initialized', 201);
    }

    /**
     * Get current active cart for kiosk session
     *
     * @queryParam include_product boolean optional Include full product details in cart items. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 123,
     *     "items": [],
     *     "subtotal": 0,
     *     "total": 0
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();
        
        $includeProduct = $request->boolean('include_product', false);

        $cart = $this->cartRepository->findActiveCart(null, $sessionId, $includeProduct ? true : null, $store->id);

        if (!$cart) {
            // Return empty cart structure
            return apiSuccess([
                'id' => null,
                'items' => [],
                'promo_code' => null,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
            ]);
        }

        // Ensure cart belongs to authenticated store
        if ($cart->store_id !== $store->id) {
            // Abandon the old cart and return empty cart
            $this->cartRepository->abandon($cart);
            return apiSuccess([
                'id' => null,
                'items' => [],
                'promo_code' => null,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
            ]);
        }

        // Recalculate totals to ensure they are up to date
        $this->cartRepository->recalculate($cart);
        $cart->refresh();
        $cart->load($this->getCartRelationships($includeProduct));

        return apiSuccess(new CartResource($cart));
    }

    /**
     * Add item to cart
     *
     * @bodyParam item_type string optional Item type: product|mix|creator_mix. Default: product. Example: mix
     * @bodyParam product_id integer required_if:item_type,product The product ID. Example: 1
     * @bodyParam quantity integer required Quantity (min 1). Example: 2
     * @bodyParam addons array optional Array of addon configurations (only for products). Example: [{"modifier_id":5,"level":2}]
     * @bodyParam note string optional Note for this cart item (max 1000 characters). Example: "Extra hot please"
     * @bodyParam configuration object required_if:item_type,mix,creator_mix Configuration snapshot for mix or creator_mix. Example: {"base_id":1,"modifiers":[{"id":2,"level":1}]}
     * @bodyParam ref_id integer optional Reference ID (mix_builder_id or creator_mix_id). Example: 10
     * @bodyParam name string optional Custom name for the item. Example: "My Custom Mix"
     * @queryParam include_product boolean optional Include full product details in response. Example: true
     *
     * @response 201 {
     *   "success": true,
     *   "message": "item_added",
     *   "data": {
     *     "id": 123,
     *     "items": [],
     *     "total": 25.50
     *   }
     * }
     */
    public function addItem(Request $request): JsonResponse
    {
        // Backwards-compatible product-only flow when item_type is not provided
        if (!$request->has('item_type')) {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
                'modifiers' => 'array',
                'note' => 'nullable|string|max:1000',
            ]);

            $store = $request->attributes->get('kiosk_store');
            $sessionId = session()->getId();

            $cart = $this->getOrCreateCart($sessionId, $store->id);

            $product = $this->productRepository->findById($validated['product_id']);
            if (!$product || !$product->is_active) {
                return apiError('PRODUCT_INACTIVE', 'product_inactive', 400);
            }

            // Legacy: use old addItem method
            $this->cartRepository->addItem(
                $cart,
                $product->id,
                $validated['quantity'],
                $validated['modifiers'] ?? [],
                $validated['note'] ?? null
            );

            $this->cartRepository->recalculate($cart);
            $cart->refresh();

            $includeProduct = $request->boolean('include_product', false);
            // Reload cart with relationships
            $cart->load($this->getCartRelationships($includeProduct));
            
            return apiSuccess(
                new CartResource($cart), 
                'item_added', 
                201
            );
        }

        // Unified add-item flow
        $addMixRequest = new AddMixToCartRequest();
        $validated = $request->validate($addMixRequest->rules());

        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();

        $cart = $this->getOrCreateCart($sessionId, $store->id);

        $itemType = $validated['item_type'];
        $quantity = $validated['quantity'];

        try {
            if ($itemType === 'product') {
                $product = $this->productRepository->findById($validated['product_id']);
                if (!$product || !$product->is_active) {
                    return apiError('PRODUCT_INACTIVE', 'product_inactive', 400);
                }

                // Validate addons if provided
                $addons = $validated['addons'] ?? [];
                if (!empty($addons)) {
                    // Normalize addons format
                    $addons = array_map(function ($addon) {
                        return [
                            'modifier_id' => $addon['modifier_id'] ?? $addon['id'] ?? null,
                            'level' => $addon['level'] ?? 1,
                        ];
                    }, $addons);
                }

                $this->cartService->addProductToCart($cart, $product, $quantity, $addons, $validated['note'] ?? null);
            } else {
                // mix or creator_mix
                $configuration = $validated['configuration'] ?? [];
                $this->cartService->addMixToCart(
                    $cart,
                    $itemType,
                    $configuration,
                    $quantity,
                    $validated['ref_id'] ?? null,
                    $validated['name'] ?? null,
                    $validated['note'] ?? null
                );
            }

            $this->cartRepository->recalculate($cart);
            $cart->refresh();

            $includeProduct = $request->boolean('include_product', false);
            // Reload cart with relationships
            $cart->load($this->getCartRelationships($includeProduct));
            
            return apiSuccess(
                new CartResource($cart),
                'item_added',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return apiError('INVALID_CONFIGURATION', $e->getMessage(), 400);
        } catch (\Exception $e) {
            return apiError('ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Update cart item quantity
     *
     * @urlParam id required The cart item ID. Example: 1
     * @bodyParam quantity integer required New quantity (min 1). Example: 3
     *
     * @response 200 {
     *   "success": true,
     *   "message": "item_updated",
     *   "data": {
     *     "id": 123,
     *     "items": [],
     *     "total": 50.00
     *   }
     * }
     */
    public function updateItem(Request $request, $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart(null, $sessionId, null, $store->id);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $cartItem = $cart->items()->findOrFail($id);
        $this->cartRepository->updateItem($cartItem, ['quantity' => $request->input('quantity')]);
        $this->cartRepository->recalculate($cart);
        $cart->refresh();

        $includeProduct = $request->boolean('include_product', false);
        $cart->load($this->getCartRelationships($includeProduct));
        
        return apiSuccess(
            new CartResource($cart), 
            'item_updated'
        );
    }

    /**
     * Remove item from cart
     *
     * @urlParam id required The cart item ID. Example: 1
     * @queryParam include_product boolean optional Include full product details in response. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "item_removed",
     *   "data": {
     *     "id": 123,
     *     "items": [],
     *     "total": 25.00
     *   }
     * }
     */
    public function removeItem(Request $request, $id): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart(null, $sessionId, null, $store->id);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $cartItem = $cart->items()->findOrFail($id);
        $this->cartRepository->removeItem($cartItem);
        $this->cartRepository->recalculate($cart);
        $cart->refresh();

        $includeProduct = $request->boolean('include_product', false);
        $cart->load($this->getCartRelationships($includeProduct));
        
        return apiSuccess(
            new CartResource($cart), 
            'item_removed'
        );
    }

    /**
     * Apply promo code to cart
     *
     * @bodyParam code string required Promo code. Example: "SAVE20"
     * @queryParam include_product boolean optional Include full product details in response. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "promo_applied",
     *   "data": {
     *     "id": 123,
     *     "promo_code": "SAVE20",
     *     "discount": 10.00,
     *     "total": 65.50
     *   }
     * }
     */
    public function applyPromo(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart(null, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        // Ensure cart belongs to authenticated store
        if ($cart->store_id !== $store->id) {
            return apiError('CART_STORE_MISMATCH', 'Cart belongs to a different store', 403);
        }

        $promoCode = $this->promoCodeRepository->findValidByCode($request->input('code'));

        if (!$promoCode) {
            return apiError('INVALID_PROMO_CODE', 'invalid_promo_code', 400);
        }

        // Ensure cart is recalculated first to get accurate subtotal
        $this->cartRepository->recalculate($cart);
        $cart->refresh();
        
        if ($cart->subtotal < $promoCode->minimum_order_amount) {
            return apiError('MINIMUM_ORDER_NOT_MET', 'minimum_order_not_met', 400);
        }

        // For guest carts (kiosk), skip customer validation
        $this->cartRepository->applyPromoCode($cart, $promoCode);
        $this->cartRepository->recalculate($cart);
        $cart->refresh();

        $includeProduct = $request->boolean('include_product', false);
        $cart->load($this->getCartRelationships($includeProduct));
        
        return apiSuccess(
            new CartResource($cart), 
            'promo_applied'
        );
    }

    /**
     * Remove promo code from cart
     *
     * @queryParam include_product boolean optional Include full product details in response. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "promo_removed",
     *   "data": {
     *     "id": 123,
     *     "promo_code": null,
     *     "total": 75.50
     *   }
     * }
     */
    public function removePromo(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart(null, $sessionId, null, $store->id);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $this->cartRepository->removePromoCode($cart);
        $this->cartRepository->recalculate($cart);
        $cart->refresh();

        $includeProduct = $request->boolean('include_product', false);
        $cart->load($this->getCartRelationships($includeProduct));
        
        return apiSuccess(
            new CartResource($cart), 
            'promo_removed'
        );
    }

    /**
     * Abandon/clear cart
     *
     * @response 200 {
     *   "success": true,
     *   "message": "cart_abandoned"
     * }
     */
    public function abandon(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart(null, $sessionId, null, $store->id);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $this->cartRepository->abandon($cart);

        return apiSuccess(null, 'cart_abandoned');
    }
}

