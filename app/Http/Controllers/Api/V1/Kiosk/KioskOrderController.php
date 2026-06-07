<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\OrderRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Integrations\Foodics\Services\FoodicsClient;
use App\Jobs\PushOrderToFoodics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * @bodyParam items array required Array of cart items. Each item should have: product_id, item_type, name, quantity, modifiers (optional), configuration (optional), note (optional). Prices are recomputed server-side and any client-supplied price/subtotal/discount/total is ignored.
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
        
        // Money fields (items.*.price, subtotal, discount, total) are accepted for
        // backward compatibility but ignored — prices are recomputed server-side.
        $rules = [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.item_type' => 'required|string|in:product,mix,creator_mix',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'sometimes|nullable|numeric|min:0',
            'items.*.modifiers' => 'nullable|array',
            'items.*.configuration' => 'nullable|array',
            'items.*.note' => 'nullable|string|max:1000',
            'subtotal' => 'sometimes|nullable|numeric|min:0',
            'discount' => 'sometimes|nullable|numeric|min:0',
            'total' => 'sometimes|nullable|numeric|min:0',
            'promo_code' => 'nullable|string|max:50',
        ];
        
        // pos_code is the legacy "tell counter staff this 4-digit code so they
        // ring the cash sale on the Foodics POS" handoff. With the kiosk now
        // pushing orders to Foodics directly, the counter loop is gone — keep
        // it nullable for backward-compat but never require it.
        $rules['pos_code'] = 'nullable|string|size:4|regex:/^[0-9]{4}$/';
        
        $request->validate($rules);

        $store = $request->attributes->get('kiosk_store');
        $items = $request->input('items');
        $paymentMethod = $request->input('payment_method');
        $promoCode = $request->input('promo_code');
        $posCode = $request->input('pos_code'); // 4-digit code for cash payments

        if (empty($items)) {
            return apiError('CART_EMPTY', 'cart_empty', 400);
        }

        try {
            // Create guest order directly from items. Prices/totals are recomputed
            // server-side inside the repository — client money fields are ignored.
            $order = $this->orderRepository->createFromItems(
                $store->id,
                $items,
                $paymentMethod,
                $promoCode,
                $posCode
            );

            // Push to Foodics inline so the kiosk can print the Foodics POS
            // number on the receipt right away. Bounded by a short timeout
            // (kiosk customers can't wait the global 30s ceiling); on any
            // failure we fall back to the OrderCreated → queued job path so
            // the kitchen still gets the order, just async.
            $this->pushToFoodicsInline($order);
            $order->refresh();

            return apiSuccess([
                'order_id' => $order->id,
                'pickup_code' => $order->pickup_code,
                'total' => (float) $order->total,
                'foodics_reference' => $order->foodics_reference,
                'foodics_order_id' => $order->foodics_order_id,
            ], 'order_created', 201);
        } catch (\InvalidArgumentException $e) {
            return apiError('INVALID_DATA', $e->getMessage(), 400);
        } catch (\Exception $e) {
            return apiError('ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Run the Foodics push job synchronously so the response carries the
     * Foodics reference. Short-circuits on any failure: the async listener
     * has already queued the same job, so the queue worker will retry. We
     * never let a Foodics outage block the kiosk customer.
     */
    private function pushToFoodicsInline(\App\Core\Models\Order $order): void
    {
        $previousTimeout = config('foodics.timeout');
        // 10s ceiling for inline path — Foodics typically answers in 1–3s.
        // The queued fallback uses the default 30s when it retries.
        config(['foodics.timeout' => (int) config('foodics.kiosk_inline_timeout', 10)]);
        try {
            (new PushOrderToFoodics($order->id))->handle(app(FoodicsClient::class));
        } catch (\Throwable $e) {
            Log::warning('FOODICS_PUSH_INLINE_FAILED_FALLBACK_QUEUED', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // The OrderCreated listener has already enqueued a PushOrderToFoodics
            // job — when it runs, it'll see the order has no foodics_order_id
            // yet and retry per the job's backoff schedule.
        } finally {
            config(['foodics.timeout' => $previousTimeout]);
        }
    }
}

