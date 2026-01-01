<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\ProductRepository;
use App\Core\Repositories\PromoCodeRepository;
use App\Http\Controllers\Controller;
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
        private PromoCodeRepository $promoCodeRepository
    ) {
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
     * @response 404 {
     *   "success": false,
     *   "error": "CART_NOT_FOUND",
     *   "message": "cart_not_found"
     * }
     */
    public function index(): JsonResponse
    {
        $customer = auth('api')->user();
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        // Recalculate totals to ensure they are up to date
        $this->cartRepository->recalculate($cart);

        return apiSuccess(new CartResource($cart->fresh(['items.product', 'promoCode'])));
    }

    /**
     * Add item to cart
     * 
     * @bodyParam product_id integer required The product ID. Example: 1
     * @bodyParam quantity integer required Quantity (min 1). Example: 2
     * @bodyParam modifiers array optional Array of modifier IDs. Example: [1, 2, 3]
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
     * 
     * @response 400 {
     *   "success": false,
     *   "error": "PRODUCT_INACTIVE",
     *   "message": "product_inactive"
     * }
     */
    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
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

        $product = $this->productRepository->findById($request->input('product_id'));
        if (!$product || !$product->is_active) {
            return apiError('PRODUCT_INACTIVE', 'product_inactive', 400);
        }

        $this->cartRepository->addItem(
            $cart,
            $product->id,
            $request->input('quantity'),
            $request->input('modifiers', [])
        );

        $this->cartRepository->recalculate($cart);

        return apiSuccess(new CartResource($cart->fresh(['items.product', 'promoCode'])), 'item_added', 201);
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

        return apiSuccess(new CartResource($cart->fresh(['items.product', 'promoCode'])), 'item_updated');
    }

    /**
     * Remove item from cart
     * 
     * @urlParam id required The cart item ID. Example: 1
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

        return apiSuccess(new CartResource($cart->fresh(['items.product', 'promoCode'])), 'item_removed');
    }

    /**
     * Apply promo code to cart
     * 
     * @bodyParam code string required Promo code. Example: "SAVE20"
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

        return apiSuccess(new CartResource($cart->fresh(['items.product', 'promoCode'])), 'promo_applied');
    }

    /**
     * Remove promo code from cart
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

        return apiSuccess(new CartResource($cart->fresh(['items.product', 'promoCode'])), 'promo_removed');
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
