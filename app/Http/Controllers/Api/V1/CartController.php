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

    public function index(): JsonResponse
    {
        $customer = auth('api')->user();
        $sessionId = session()->getId();

        $cart = $this->cartRepository->findActiveCart($customer?->id, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        return apiSuccess(new CartResource($cart));
    }

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
