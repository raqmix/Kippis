<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\OrderRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Kiosk Order APIs
 */
class KioskOrderController extends Controller
{
    public function __construct(
        private OrderRepository $orderRepository,
        private CartRepository $cartRepository
    ) {
    }

    /**
     * Checkout cart and create guest order for kiosk
     *
     * Creates an order from the current session-based cart. Order will have customer_id = null (guest order).
     *
     * @bodyParam payment_method string required Payment method. Options: `cash`, `card`, `online`. Example: cash
     *
     * @response 201 {
     *   "success": true,
     *   "message": "order_created",
     *   "data": {
     *     "order_id": 123,
     *     "pickup_code": "ABC123",
     *     "total": 75.50
     *   }
     * }
     *
     * @response 400 {
     *   "success": false,
     *   "error": "CART_EMPTY",
     *   "message": "cart_empty"
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "error": "CART_NOT_FOUND",
     *   "message": "cart_not_found"
     * }
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:cash,card,online',
        ]);

        $store = $request->attributes->get('kiosk_store');
        $sessionId = session()->getId();

        // Get session-based cart
        $cart = $this->cartRepository->findActiveCart(null, $sessionId);

        if (!$cart) {
            return apiError('CART_NOT_FOUND', 'cart_not_found', 404);
        }

        // Ensure cart belongs to authenticated store
        if ($cart->store_id !== $store->id) {
            return apiError('CART_STORE_MISMATCH', 'Cart belongs to a different store', 403);
        }

        if ($cart->items->isEmpty()) {
            return apiError('CART_EMPTY', 'cart_empty', 400);
        }

        // Recalculate cart totals before creating order to ensure they are accurate
        $this->cartRepository->recalculate($cart);
        $cart->refresh();

        // Create guest order (customer_id will be null)
        $order = $this->orderRepository->createFromCart($cart, $request->input('payment_method'));

        $this->cartRepository->abandon($cart);

        return apiSuccess([
            'order_id' => $order->id,
            'pickup_code' => $order->pickup_code,
            'total' => (float) $order->total,
        ], 'order_created', 201);
    }
}

