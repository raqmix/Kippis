<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\OrderRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Orders APIs
 */
class OrderController extends Controller
{
    public function __construct(
        private OrderRepository $orderRepository,
        private CartRepository $cartRepository
    ) {
    }

    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:cash,card,online',
        ]);

        $customer = auth('api')->user();
        if (!$customer) {
            return apiError('UNAUTHORIZED', 'unauthorized', 401);
        }

        $cart = $this->cartRepository->findActiveCart($customer->id, null);

        if (!$cart || $cart->items->isEmpty()) {
            return apiError('CART_EMPTY', 'cart_empty', 400);
        }

        $order = $this->orderRepository->createFromCart($cart, $request->input('payment_method'));

        $this->cartRepository->abandon($cart);

        return apiSuccess([
            'order_id' => $order->id,
            'pickup_code' => $order->pickup_code,
            'total' => (float) $order->total,
        ], 'order_created', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $customer = auth('api')->user();
        $filters = [
            'status' => $request->query('status', 'active'),
            'payment_method' => $request->query('payment_method'),
            'store_id' => $request->query('store_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'total_min' => $request->query('total_min'),
            'total_max' => $request->query('total_max'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        $perPage = min($request->query('per_page', 15), 100);
        $orders = $this->orderRepository->getPaginatedForCustomer($customer->id, $filters, $perPage);

        return apiSuccess(
            OrderResource::collection($orders),
            null,
            200,
            [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ]
        );
    }

    public function show($id): JsonResponse
    {
        $customer = auth('api')->user();
        $order = $this->orderRepository->findByIdForCustomer($id, $customer->id);

        if (!$order) {
            return apiError('ORDER_NOT_FOUND', 'order_not_found', 404);
        }

        return apiSuccess(new OrderResource($order));
    }

    public function tracking($id): JsonResponse
    {
        $customer = auth('api')->user();
        $order = $this->orderRepository->findByIdForCustomer($id, $customer->id);

        if (!$order) {
            return apiError('ORDER_NOT_FOUND', 'order_not_found', 404);
        }

        return apiSuccess([
            'status' => $order->status,
            'pickup_code' => $order->pickup_code,
            'status_history' => [
                ['status' => 'received', 'at' => $order->created_at->toIso8601String()],
            ],
        ]);
    }

    /**
     * Reorder an existing order.
     * 
     * Create a new cart with the same items from a previous order, allowing the customer to reorder easily.
     * 
     * @urlParam id int required The order ID to reorder. Example: 123
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "cart_recreated",
     *   "data": {
     *     "cart_id": 456,
     *     "store_id": 1,
     *     "items_count": 3,
     *     "subtotal": 75.00,
     *     "total": 75.00
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "ORDER_NOT_FOUND",
     *     "message": "Order not found."
     *   }
     * }
     */
    public function reorder($id): JsonResponse
    {
        $customer = auth('api')->user();
        $order = $this->orderRepository->findByIdForCustomer($id, $customer->id);

        if (!$order) {
            return apiError('ORDER_NOT_FOUND', 'order_not_found', 404);
        }

        // Check if order is cancelled
        if ($order->status === 'cancelled') {
            return apiError('ORDER_CANCELLED', 'cannot_reorder_cancelled_order', 400);
        }

        // Create new cart from order
        $cart = $this->cartRepository->create([
            'customer_id' => $customer->id,
            'store_id' => $order->store_id,
        ]);

        $itemsAdded = 0;
        foreach ($order->items_snapshot as $item) {
            // Verify product still exists and is active
            $product = \App\Core\Models\Product::find($item['product_id']);
            if (!$product || !$product->is_active) {
                continue; // Skip inactive or deleted products
            }

            $this->cartRepository->addItem(
                $cart,
                $item['product_id'],
                $item['quantity'],
                $item['modifiers'] ?? []
            );
            $itemsAdded++;
        }

        if ($itemsAdded === 0) {
            // Delete empty cart
            $cart->delete();
            return apiError('NO_VALID_ITEMS', 'no_valid_items_to_reorder', 400);
        }

        $this->cartRepository->recalculate($cart);

        return apiSuccess([
            'cart_id' => $cart->id,
            'store_id' => $cart->store_id,
            'items_count' => $itemsAdded,
            'subtotal' => (float) $cart->subtotal,
            'total' => (float) $cart->total,
        ], 'cart_recreated', 201);
    }
}
