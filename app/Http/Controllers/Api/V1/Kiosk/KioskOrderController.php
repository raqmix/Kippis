<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Models\Order;
use App\Core\Repositories\CartRepository;
use App\Core\Repositories\OrderRepository;
use App\Exceptions\AppOnlyProductException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Integrations\Foodics\Services\FoodicsClient;
use App\Jobs\PushOrderToFoodics;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Kiosk Order APIs
 */
class KioskOrderController extends Controller
{
    /** Request header carrying the kiosk's per-checkout idempotency key. */
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    /** How long a key replays the original order. */
    public const IDEMPOTENCY_TTL_HOURS = 24;

    /** Matches the orders.idempotency_key column length. */
    private const IDEMPOTENCY_KEY_MAX = 100;

    /** Delay before the safety-net queued push after an inline fallback. */
    private const FALLBACK_PUSH_DELAY_SECONDS = 30;

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
     * @header Idempotency-Key Optional opaque key (the kiosk sends a UUID v4) per checkout attempt. A repeat with the same key at the same store within 24 hours returns the original order (same 201 body, header `Idempotent-Replayed: true`) without creating or pushing a new one. Max 100 characters. Example: 3f0c7a52-8a43-4f0e-9a51-2d4c1f6b9e10
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
        $store = $request->attributes->get('kiosk_store');

        // Idempotency comes first: a retry must get the original order back
        // even if the catalog changed in between (item switched off, price
        // changed) and the same body would no longer validate.
        $idempotencyKey = $request->header(self::IDEMPOTENCY_HEADER);
        $idempotencyKey = is_string($idempotencyKey) ? trim($idempotencyKey) : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }
        if ($idempotencyKey !== null) {
            if (mb_strlen($idempotencyKey) > self::IDEMPOTENCY_KEY_MAX) {
                return apiError('VALIDATION_ERROR', 'idempotency_key_too_long', 422);
            }
            if ($existing = $this->findIdempotentOrder((int) $store->id, $idempotencyKey)) {
                return $this->checkoutResponse($existing, replayed: true);
            }
        }

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

        $items = $request->input('items');
        $paymentMethod = $request->input('payment_method');
        $promoCode = $request->input('promo_code');
        $posCode = $request->input('pos_code'); // 4-digit code for cash payments

        if (empty($items)) {
            return apiError('CART_EMPTY', 'cart_empty', 400);
        }

        // The kiosk caches its catalog, so an item switched off at this
        // branch after the last refresh can still be in the cart.
        // Inactive products keep their existing 400 from the repository.
        $unavailableResponse = $this->guardItemsAvailableAtStore(
            collect($items)
                ->filter(fn ($item) => ($item['item_type'] ?? 'product') === 'product' && ! empty($item['product_id']))
                ->map(fn ($item) => [
                    'product_id' => (int) $item['product_id'],
                    'name' => $item['name'] ?? null,
                ])
                ->values(),
            (int) $store->id,
            requireActive: false
        );
        if ($unavailableResponse) {
            return $unavailableResponse;
        }

        try {
            // Create guest order directly from items. Prices/totals are recomputed
            // server-side inside the repository — client money fields are ignored.
            try {
                $order = $this->orderRepository->createFromItems(
                    $store->id,
                    $items,
                    $paymentMethod,
                    $promoCode,
                    $posCode,
                    $idempotencyKey
                );
            } catch (UniqueConstraintViolationException $e) {
                // A concurrent request with the same key won the insert (the
                // unique index on store_id + idempotency_key). Ours rolled
                // back; answer with the winner's order.
                $winner = $idempotencyKey !== null
                    ? $this->findIdempotentOrder((int) $store->id, $idempotencyKey)
                    : null;
                if (! $winner) {
                    throw $e;
                }

                return $this->checkoutResponse($winner, replayed: true);
            }

            // Push to Foodics inline so the kiosk can print the Foodics POS
            // number on the receipt right away. Bounded by a short timeout
            // (kiosk customers can't wait the global 30s ceiling); on any
            // failure we fall back to the OrderCreated → queued job path so
            // the kitchen still gets the order, just async.
            $this->pushToFoodicsInline($order);
            $order->refresh();

            return $this->checkoutResponse($order);
        } catch (AppOnlyProductException $e) {
            return apiError('APP_ONLY_PRODUCT', 'app_only_product', 422);
        } catch (\InvalidArgumentException $e) {
            return apiError('INVALID_DATA', $e->getMessage(), 400);
        } catch (\Exception $e) {
            return apiError('ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * The 201 body for a created order. A replay (same Idempotency-Key)
     * returns exactly this shape and status, with the order's current
     * Foodics reference, plus an Idempotent-Replayed header.
     */
    private function checkoutResponse(Order $order, bool $replayed = false): JsonResponse
    {
        $response = apiSuccess([
            'order_id' => $order->id,
            'pickup_code' => $order->pickup_code,
            'total' => (float) $order->total,
            'foodics_reference' => $order->foodics_reference,
            'foodics_order_id' => $order->foodics_order_id,
        ], 'order_created', 201);

        if ($replayed) {
            $response->headers->set('Idempotent-Replayed', 'true');
            Log::info('KIOSK_CHECKOUT_IDEMPOTENT_REPLAY', [
                'order_id' => $order->id,
                'store_id' => $order->store_id,
            ]);
        }

        return $response;
    }

    /**
     * The order this store already created for this key in the last 24h.
     *
     * The unique index is permanent but the replay window is not: a key
     * older than the TTL is released (set to NULL on the old order) so the
     * new checkout can use it. Kiosk keys are UUIDs, so in practice this
     * only matters for a cart left open across a day.
     */
    private function findIdempotentOrder(int $storeId, string $key): ?Order
    {
        $order = Order::where('store_id', $storeId)
            ->where('idempotency_key', $key)
            ->first();

        if (! $order) {
            return null;
        }

        if ($order->created_at && $order->created_at->lt(now()->subHours(self::IDEMPOTENCY_TTL_HOURS))) {
            Order::whereKey($order->id)
                ->where('idempotency_key', $key)
                ->update(['idempotency_key' => null]);

            return null;
        }

        return $order;
    }

    /**
     * Run the Foodics push job synchronously so the response carries the
     * Foodics reference — but only if it can go right now. The client is
     * in fail-fast mode: no waiting on the shared rate limiter, no 429/503
     * retry sleeps, and the whole push (every HTTP call in it) is capped at
     * foodics.kiosk_inline_timeout seconds (default 8). Anything slower is
     * left to the queue so the kiosk (30s HTTP timeout) always gets its
     * answer and the customer doesn't retry into a duplicate order.
     */
    private function pushToFoodicsInline(Order $order): void
    {
        $budget = (float) config('foodics.kiosk_inline_timeout', 8);

        try {
            $client = app(FoodicsClient::class)->withDeadline($budget);
            (new PushOrderToFoodics($order->id))->handle($client);
        } catch (\Throwable $e) {
            Log::warning('FOODICS_PUSH_INLINE_FAILED_FALLBACK_QUEUED', [
                'order_id' => $order->id,
                'error_class' => $e::class,
                'error' => $e->getMessage(),
            ]);

            // The OrderCreated listener already queued a PushOrderToFoodics
            // job, but a worker that picked it up while we held the push
            // lock skipped it (FOODICS_PUSH_SKIPPED_LOCKED) and won't retry.
            // Queue one more as a safety net. The job is idempotent: it exits
            // if the order already has a foodics_order_id, and the lock stops
            // two copies pushing at once.
            try {
                PushOrderToFoodics::dispatch($order->id)
                    ->delay(now()->addSeconds(self::FALLBACK_PUSH_DELAY_SECONDS));
            } catch (\Throwable $dispatchError) {
                Log::error('FOODICS_PUSH_FALLBACK_DISPATCH_FAILED', [
                    'order_id' => $order->id,
                    'error' => $dispatchError->getMessage(),
                ]);
            }
        }
    }
}
