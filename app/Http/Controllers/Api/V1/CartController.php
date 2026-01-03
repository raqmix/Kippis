<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\ProductRepository;
use App\Core\Repositories\PromoCodeRepository;
use App\Services\MixPriceCalculator;
use App\Services\CartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddProductToCartRequest;
use App\Http\Requests\Api\V1\AddMixToCartRequest;
use App\Http\Resources\Api\V1\CartResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Cart APIs
 */
class CartController extends Controller
{
    public function __construct(
        private CartRepository $cartRepository,
        private ProductRepository $productRepository,
        private PromoCodeRepository $promoCodeRepository,
        private MixPriceCalculator $mixPriceCalculator,
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
     * Initialize a new cart
     *
     * @bodyParam store_id integer required The store ID. Example: 1
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
        $request->validate([
            'store_id' => 'required|exists:stores,id',
        ]);

        $customer = auth('api')->user();
        $sessionId = $customer ? null : session()->getId();

        $cart = $this->cartRepository->create([
            'customer_id' => $customer?->id,
            'session_id' => $sessionId,
            'store_id' => $request->input('store_id'),
        ]);

        return apiSuccess([
            'cart_id' => $cart->id,
            'session_id' => $sessionId,
        ], 'cart_initialized', 201);
    }

    /**
     * Get current active cart
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
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 123,
     *     "items": [
     *       {
     *         "id": 1,
     *         "item_type": "product",
     *         "name": "Espresso",
     *         "quantity": 1,
     *         "price": 12.00,
     *         "product": {
     *           "id": 19,
     *           "name": "Espresso",
     *           "name_ar": "إسبريسو",
     *           "name_en": "Espresso",
     *           "description": "Strong and bold espresso shot",
     *           "description_ar": "جرعة إسبريسو قوية وجريئة",
     *           "description_en": "Strong and bold espresso shot",
     *           "image": null,
     *           "base_price": 12,
     *           "category": {
     *             "id": 1,
     *             "name": "Hot Drinks"
     *           },
     *           "external_source": "local",
     *           "allowed_addons": []
     *         }
     *       }
     *     ],
     *     "subtotal": 12.00,
     *     "total": 12.00
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "error": "CART_NOT_FOUND",
     *   "message": "cart_not_found"
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $customer = auth('api')->user();
        $sessionId = session()->getId();
        
        $includeProduct = $request->boolean('include_product', false);

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId, $includeProduct);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        // Recalculate totals to ensure they are up to date
        $this->cartRepository->recalculate($cart);

        return apiSuccess(new CartResource($cart->fresh($this->getCartRelationships($includeProduct))));
    }

    /**
     * Add item to cart
     *
     * Add a product, mix, or creator mix to the cart. Price is computed ONCE when adding and stored in cart_item.price.
     * Cart totals are calculated by summing stored item prices (no repricing after save).
     *
     * **Backward Compatibility**: If `item_type` is not provided, the old format is used: `{"product_id":1,"quantity":2}`
     *
     * **Product Details**: Use `include_product=true` query parameter to include full product details with `allowed_addons` in the response.
     *
     * **Request Examples:**
     *
     * 1. **Product (simple)**:
     * ```json
     * {
     *   "item_type": "product",
     *   "product_id": 1,
     *   "quantity": 2
     * }
     * ```
     *
     * 2. **Product with addons**:
     * ```json
     * {
     *   "item_type": "product",
     *   "product_id": 1,
     *   "quantity": 1,
     *   "addons": [
     *     {"modifier_id": 5, "level": 2},
     *     {"modifier_id": 8, "level": 1}
     *   ]
     * }
     * ```
     *
     * 3. **Mix (custom mix)**:
     * ```json
     * {
     *   "item_type": "mix",
     *   "quantity": 1,
     *   "configuration": {
     *     "base_id": 1,
     *     "modifiers": [
     *       {"id": 2, "level": 3},
     *       {"id": 5, "level": 1}
     *     ],
     *     "extras": [3, 4]
     *   },
     *   "name": "My Custom Mix"
     * }
     * ```
     *
     * 4. **Creator Mix**:
     * ```json
     * {
     *   "item_type": "creator_mix",
     *   "quantity": 1,
     *   "configuration": {
     *     "base_id": 1,
     *     "modifiers": [{"id": 2, "level": 2}],
     *     "extras": []
     *   },
     *   "ref_id": 10,
     *   "name": "Berry Blast Mix"
     * }
     * ```
     *
     * **Modifier Levels**: Level must be between 0 and modifier.max_level. Level 0 means no modifier applied.
     * Price calculation: `modifier.price * level`
     *
     * @bodyParam item_type string optional Item type: product|mix|creator_mix. Default: product (if not provided, uses legacy format). Example: mix
     * @bodyParam product_id integer required_if:item_type,product The product ID. Example: 1
     * @bodyParam quantity integer required Quantity (min 1). Example: 2
     * @bodyParam addons array optional Array of addon configurations (only for products). Example: [{"modifier_id":5,"level":2}]
     * @bodyParam addons.*.modifier_id integer required Modifier ID assigned to product as addon. Example: 5
     * @bodyParam addons.*.level integer optional Modifier level (0 to max_level). Default: 1. Example: 2
     * @bodyParam configuration object required_if:item_type,mix,creator_mix Configuration snapshot for mix or creator_mix. Example: {"base_id":1,"modifiers":[{"id":2,"level":1}]}
     * @bodyParam configuration.base_id integer optional Base product ID (preferred). Must be a product with product_kind = mix_base. Example: 1
     * @bodyParam configuration.base_price number optional Deprecated. Raw base price for backward compatibility. Example: 15.00
     * @bodyParam configuration.builder_id integer optional Mix builder ID to validate base belongs to builder. Example: 1
     * @bodyParam configuration.mix_builder_id integer optional Alias for builder_id. Example: 1
     * @bodyParam configuration.modifiers array optional Array of modifier configurations. Example: [{"id":2,"level":1}]
     * @bodyParam configuration.modifiers.*.id integer required Modifier ID. Example: 2
     * @bodyParam configuration.modifiers.*.level integer optional Modifier level (0 to max_level). Default: 1. Example: 1
     * @bodyParam configuration.extras array optional Array of extra product IDs. Example: [3,4]
     * @bodyParam ref_id integer optional Reference ID (mix_builder_id or creator_mix_id). Example: 10
     * @bodyParam name string optional Custom name for the item. Example: "My Custom Mix"
     * @queryParam include_product boolean optional Include full product details in response. Example: true
     *
     * @response 201 {
     *   "success": true,
     *   "message": "item_added",
     *   "data": {
     *     "id": 123,
     *     "items": [
     *       {
     *         "id": 1,
     *         "item_type": "product",
     *         "name": "Product Name",
     *         "quantity": 2,
     *         "price": 25.50,
     *         "addons": [{"modifier_id": 5, "level": 2}]
     *       }
     *     ],
     *     "subtotal": 25.50,
     *     "discount": 0.00,
     *     "total": 25.50
     *   }
     * }
     *
     * @response 400 {
     *   "success": false,
     *   "error": "PRODUCT_INACTIVE",
     *   "message": "product_inactive"
     * }
     *
     * @response 400 {
     *   "success": false,
     *   "error": "INVALID_CONFIGURATION",
     *   "message": "Modifier level 5 exceeds maximum level 3"
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
            ]);

            $customer = auth('api')->user();
            $sessionId = session()->getId();

            $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

            if (!$cart) {
                return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
            }

            $product = $this->productRepository->findById($validated['product_id']);
            if (!$product || !$product->is_active) {
                return apiError('PRODUCT_INACTIVE', 'product_inactive', 400);
            }

            // Legacy: use old addItem method (no addons support)
            $this->cartRepository->addItem(
                $cart,
                $product->id,
                $validated['quantity'],
                $validated['modifiers'] ?? []
            );

            $this->cartRepository->recalculate($cart);

            $includeProduct = $request->boolean('include_product', false);
            return apiSuccess(
                new CartResource($cart->fresh($this->getCartRelationships($includeProduct))), 
                'item_added', 
                201
            );
        }

        // Unified add-item flow
        $addMixRequest = new AddMixToCartRequest();
        $validated = $addMixRequest->validate($request);

        $customer = auth('api')->user();
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

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

                $this->cartService->addProductToCart($cart, $product, $quantity, $addons);
            } else {
                // mix or creator_mix
                $configuration = $validated['configuration'] ?? [];
                $this->cartService->addMixToCart(
                    $cart,
                    $itemType,
                    $configuration,
                    $quantity,
                    $validated['ref_id'] ?? null,
                    $validated['name'] ?? null
                );
            }

            $this->cartRepository->recalculate($cart);

            $includeProduct = $request->boolean('include_product', false);
            return apiSuccess(
                new CartResource($cart->fresh($this->getCartRelationships($includeProduct))),
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

        $customer = auth('api')->user();
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $cartItem = $cart->items()->findOrFail($id);
        $this->cartRepository->updateItem($cartItem, ['quantity' => $request->input('quantity')]);
        $this->cartRepository->recalculate($cart);

        $includeProduct = $request->boolean('include_product', false);
        return apiSuccess(
            new CartResource($cart->fresh($this->getCartRelationships($includeProduct))), 
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
    public function removeItem($id): JsonResponse
    {
        $customer = auth('api')->user();
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $cartItem = $cart->items()->findOrFail($id);
        $this->cartRepository->removeItem($cartItem);
        $this->cartRepository->recalculate($cart);

        $includeProduct = $request->boolean('include_product', false);
        return apiSuccess(
            new CartResource($cart->fresh($this->getCartRelationships($includeProduct))), 
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
     *
     * @response 400 {
     *   "success": false,
     *   "error": "INVALID_PROMO_CODE",
     *   "message": "invalid_promo_code"
     * }
     */
    public function applyPromo(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $customer = auth('api')->user();
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $promoCode = $this->promoCodeRepository->findValidByCode($request->input('code'));

        if (!$promoCode) {
            return apiError('INVALID_PROMO_CODE', 'invalid_promo_code', 400);
        }

        if ($cart->subtotal < $promoCode->minimum_order_amount) {
            return apiError('MINIMUM_ORDER_NOT_MET', 'minimum_order_not_met', 400);
        }

        if ($customer && !$this->promoCodeRepository->isValidForCustomer($promoCode, $customer->id, $cart->subtotal)) {
            return apiError('INVALID_PROMO_CODE', 'invalid_promo_code', 400);
        }

        $this->cartRepository->applyPromoCode($cart, $promoCode);
        $this->cartRepository->recalculate($cart);

        $includeProduct = $request->boolean('include_product', false);
        return apiSuccess(
            new CartResource($cart->fresh($this->getCartRelationships($includeProduct))), 
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
    public function removePromo(): JsonResponse
    {
        $customer = auth('api')->user();
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $this->cartRepository->removePromoCode($cart);
        $this->cartRepository->recalculate($cart);

        $includeProduct = request()->boolean('include_product', false);
        return apiSuccess(
            new CartResource($cart->fresh($this->getCartRelationships($includeProduct))), 
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
    public function abandon(): JsonResponse
    {
        $customer = auth('api')->user();
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        $this->cartRepository->abandon($cart);

        return apiSuccess(null, 'cart_abandoned');
    }
}
