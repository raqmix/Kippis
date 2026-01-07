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
     * Creates an order from locally managed cart items. Order will have customer_id = null (guest order).
     *
     * @bodyParam payment_method string required Payment method. Options: `cash`, `card`, `online`. Example: cash
     * @bodyParam items array required Array of cart items. Each item should have: product_id, item_type, name, quantity, price, modifiers (optional), configuration (optional), note (optional)
     * @bodyParam subtotal float required Cart subtotal
     * @bodyParam discount float required Cart discount (from promo code if applicable)
     * @bodyParam total float required Cart total
     * @bodyParam promo_code string optional Promo code to apply
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
     *   "error": "VALIDATION_ERROR",
     *   "message": "Validation failed."
     * }
     */
    public function checkout(Request $request): JsonResponse
    {
        // First validate payment_method to know if we need pos_code
        $request->validate([
            'payment_method' => 'required|string|in:cash,card,online',
        ]);
        
        $paymentMethod = $request->input('payment_method');
        
        $rules = [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.item_type' => 'required|string|in:product,mix,creator_mix',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.modifiers' => 'nullable|array',
            'items.*.configuration' => 'nullable|array',
            'items.*.note' => 'nullable|string|max:1000',
            'subtotal' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'promo_code' => 'nullable|string|max:50',
        ];
        
        // POS code is required for cash payments
        if ($paymentMethod === 'cash') {
            $rules['pos_code'] = 'required|string|size:4|regex:/^[0-9]{4}$/';
        } else {
            $rules['pos_code'] = 'nullable|string|size:4|regex:/^[0-9]{4}$/';
        }
        
        $request->validate($rules);

        $store = $request->attributes->get('kiosk_store');
        $items = $request->input('items');
        $paymentMethod = $request->input('payment_method');
        $subtotal = (float) $request->input('subtotal');
        $discount = (float) $request->input('discount');
        $total = (float) $request->input('total');
        $promoCode = $request->input('promo_code');
        $posCode = $request->input('pos_code'); // 4-digit code for cash payments

        if (empty($items)) {
            return apiError('CART_EMPTY', 'cart_empty', 400);
        }

        try {
            // Create guest order directly from items
            $order = $this->orderRepository->createFromItems(
                $store->id,
                $items,
                $paymentMethod,
                $subtotal,
                $discount,
                $total,
                $promoCode,
                $posCode
            );

            return apiSuccess([
                'order_id' => $order->id,
                'pickup_code' => $order->pickup_code,
                'total' => (float) $order->total,
            ], 'order_created', 201);
        } catch (\InvalidArgumentException $e) {
            return apiError('INVALID_DATA', $e->getMessage(), 400);
        } catch (\Exception $e) {
            return apiError('ERROR', $e->getMessage(), 500);
        }
    }
}

