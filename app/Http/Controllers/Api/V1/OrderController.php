<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\OrderRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function reorder($id): JsonResponse
    {
        $customer = auth('api')->user();
        $order = $this->orderRepository->findByIdForCustomer($id, $customer->id);

        if (!$order) {
            return apiError('ORDER_NOT_FOUND', 'order_not_found', 404);
        }

        // Create new cart from order
        $cart = $this->cartRepository->create([
            'customer_id' => $customer->id,
            'store_id' => $order->store_id,
        ]);

        foreach ($order->items_snapshot as $item) {
            $this->cartRepository->addItem(
                $cart,
                $item['product_id'],
                $item['quantity'],
                $item['modifiers'] ?? []
            );
        }

        $this->cartRepository->recalculate($cart);

        return apiSuccess([
            'cart_id' => $cart->id,
        ], 'cart_recreated', 201);
    }
}
