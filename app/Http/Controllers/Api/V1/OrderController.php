<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\OrderRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Checkout cart and create order
     * 
     * @authenticated
     * 
     * @bodyParam payment_method string required Payment method. Options: `cash`, `card`, `online`. Example: cash
     * @bodyParam store_id integer optional Store ID for the order. If not provided, uses the cart's store_id. Example: 1
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
     * @response 401 {
     *   "success": false,
     *   "error": "UNAUTHORIZED",
     *   "message": "unauthorized"
     * }
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:cash,card,online',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $customer = auth('api')->user();
        if (!$customer) {
            return apiError('UNAUTHORIZED', 'unauthorized', 401);
        }

        $cart = $this->cartRepository->findActiveCart($customer->id);

        if (!$cart || $cart->items->isEmpty()) {
            return apiError('CART_EMPTY', 'cart_empty', 400);
        }

        // Recalculate cart totals before creating order to ensure they are accurate
        $this->cartRepository->recalculate($cart);
        $cart->refresh();

        $storeId = $request->input('store_id') ?? $cart->store_id;
        $order = $this->orderRepository->createFromCart($cart, $request->input('payment_method'), $storeId);

        $this->cartRepository->abandon($cart);

        return apiSuccess([
            'order_id' => $order->id,
            'pickup_code' => $order->pickup_code,
            'total' => (float) $order->total,
        ], 'order_created', 201);
    }

    /**
     * Get list of customer orders
     * 
     * @authenticated
     * 
     * @queryParam status string optional Filter by status. Use "active" to get orders that are active now (not completed or cancelled). Use "past" to get all orders that are not active (completed or cancelled). You can also filter by specific status: received, mixing, ready, completed, cancelled. Default: "active". Example: "past"
     * @queryParam payment_method string optional Filter by payment method. Example: "cash"
     * @queryParam store_id integer optional Filter by store ID. Example: 1
     * @queryParam date_from date optional Filter orders from date (Y-m-d). Example: "2025-01-01"
     * @queryParam date_to date optional Filter orders to date (Y-m-d). Example: "2025-12-31"
     * @queryParam total_min number optional Minimum order total. Example: 10.50
     * @queryParam total_max number optional Maximum order total. Example: 100.00
     * @queryParam sort_by string optional Sort field. Default: "created_at". Example: "total"
     * @queryParam sort_order string optional Sort order (asc, desc). Default: "desc". Example: "asc"
     * @queryParam per_page integer optional Items per page (max 100). Default: 15. Example: 20
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 123,
     *       "pickup_code": "ABC123",
     *       "status": "completed",
     *       "total": 75.50
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 50,
     *     "last_page": 4
     *   }
     * }
     */
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

    /**
     * Get single order by ID
     * 
     * @authenticated
     * 
     * @urlParam id required The order ID. Example: 123
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 123,
     *     "pickup_code": "ABC123",
     *     "status": "completed",
     *     "total": 75.50,
     *     "items": []
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": "ORDER_NOT_FOUND",
     *   "message": "order_not_found"
     * }
     */
    public function show($id): JsonResponse
    {
        $customer = auth('api')->user();
        $order = $this->orderRepository->findByIdForCustomer($id, $customer->id);

        if (!$order) {
            return apiError('ORDER_NOT_FOUND', 'order_not_found', 404);
        }

        return apiSuccess(new OrderResource($order));
    }

    /**
     * Get order tracking information
     * 
     * @authenticated
     * 
     * @urlParam id required The order ID. Example: 123
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "status": "completed",
     *     "pickup_code": "ABC123",
     *     "status_history": [
     *       {
     *         "status": "received",
     *         "at": "2025-12-21T10:00:00Z"
     *       }
     *     ]
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": "ORDER_NOT_FOUND",
     *   "message": "order_not_found"
     * }
     */
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

    /**
     * Download order receipt as PDF
     * 
     * @authenticated
     * 
     * @urlParam id required The order ID. Example: 123
     * 
     * @response 200
     * The response will be a PDF file download.
     * 
     * @response 404 {
     *   "success": false,
     *   "error": "ORDER_NOT_FOUND",
     *   "message": "order_not_found"
     * }
     */
    public function downloadPdf($id): Response|JsonResponse
    {
        $customer = auth('api')->user();
        $order = $this->orderRepository->findByIdForCustomer($id, $customer->id);

        if (!$order) {
            return apiError('ORDER_NOT_FOUND', 'order_not_found', 404);
        }

        // Load relationships
        $order->load(['store', 'customer', 'promoCode']);

        // Generate PDF
        $pdf = Pdf::loadView('orders.receipt', [
            'order' => $order,
            'store' => $order->store,
            'customer' => $order->customer,
            'htmlDir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
        ]);

        // Set PDF options for better mobile compatibility
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('enable-local-file-access', true);

        // Generate filename
        $filename = 'order-' . $order->id . '-' . $order->pickup_code . '.pdf';

        // Return PDF download response with proper headers for mobile compatibility
        return $pdf->download($filename);
    }
}
