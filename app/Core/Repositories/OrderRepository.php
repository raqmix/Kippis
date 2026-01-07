<?php

namespace App\Core\Repositories;

use App\Core\Models\Order;
use App\Core\Models\PaymentMethod;
use App\Core\Models\PromoCodeUsage;
use App\Events\OrderCreated;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class OrderRepository
{
    /**
     * Create order from cart.
     *
     * @param \App\Core\Models\Cart $cart
     * @param int $paymentMethodId Payment method ID from payment_methods table
     * @param int|null $storeId Optional store ID. If provided, overrides the cart's store_id.
     * @return Order
     */
    public function createFromCart(\App\Core\Models\Cart $cart, int $paymentMethodId, ?int $storeId = null): Order
    {
        $pickupCode = strtoupper(Str::random(6));

        // Get payment method details
        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);

        $order = Order::create([
            'store_id' => $storeId ?? $cart->store_id,
            'customer_id' => $cart->customer_id,
            'status' => 'received',
            'total' => $cart->total,
            'subtotal' => $cart->subtotal,
            'discount' => $cart->discount,
            'payment_method' => $paymentMethod->code, // Keep for backward compatibility
            'payment_method_id' => $paymentMethodId,
            'pickup_code' => $pickupCode,
            'items_snapshot' => $cart->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'item_type' => $item->item_type ?? 'product',
                    'name' => $item->name ?? ($item->product ? $item->product->getName(app()->getLocale()) : 'Unknown'),
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'modifiers' => $item->modifiers_snapshot,
                    'configuration' => $item->configuration,
                ];
            })->toArray(),
            'modifiers_snapshot' => $cart->items->pluck('modifiers_snapshot')->toArray(),
            'promo_code_id' => $cart->promo_code_id,
            'promo_discount' => $cart->discount,
        ]);

        // Record promo code usage (only if customer is authenticated)
        if ($cart->promoCode && $cart->customer_id) {
            PromoCodeUsage::create([
                'promo_code_id' => $cart->promoCode->id,
                'customer_id' => $cart->customer_id,
                'order_id' => $order->id,
                'discount_amount' => $cart->discount,
                'used_at' => now(),
            ]);

            $cart->promoCode->increment('used_count');
        } elseif ($cart->promoCode) {
            // For guest orders, still increment usage count but don't track customer usage
            $cart->promoCode->increment('used_count');
        }

        // Dispatch event for real-time notification
        event(new OrderCreated($order));

        return $order;
    }

    /**
     * Create order directly from items array (for kiosk local cart).
     *
     * @param int $storeId
     * @param array $items Array of items with: product_id, item_type, name, quantity, price, modifiers, configuration, note
     * @param string $paymentMethodCode Payment method code (cash, card, online)
     * @param float $subtotal Calculated subtotal
     * @param float $discount Calculated discount (from promo code if applicable)
     * @param float $total Calculated total
     * @param string|null $promoCode Optional promo code to apply
     * @return Order
     */
    public function createFromItems(
        int $storeId,
        array $items,
        string $paymentMethodCode,
        float $subtotal,
        float $discount,
        float $total,
        ?string $promoCode = null,
        ?string $posCode = null
    ): Order {
        $pickupCode = strtoupper(Str::random(6));

        // Get payment method by code
        $paymentMethod = PaymentMethod::where('code', $paymentMethodCode)->first();
        if (!$paymentMethod) {
            throw new \InvalidArgumentException("Invalid payment method: {$paymentMethodCode}");
        }

        // Find promo code if provided
        $promoCodeModel = null;
        if ($promoCode) {
            $promoCodeModel = \App\Core\Models\PromoCode::where('code', strtoupper($promoCode))
                ->valid()
                ->first();

            // If promo code found, validate minimum order amount
            if ($promoCodeModel && $subtotal < $promoCodeModel->minimum_order_amount) {
                throw new \InvalidArgumentException("Minimum order amount not met for promo code");
            }
        }

        // For cash payments with POS code, set status to pending_payment
        // Order will be updated to 'received' when cashier confirms payment
        $status = ($paymentMethodCode === 'cash' && $posCode) ? 'pending_payment' : 'received';
        
        $order = Order::create([
            'store_id' => $storeId,
            'customer_id' => null, // Guest order for kiosk
            'status' => $status,
            'total' => $total,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'payment_method' => $paymentMethodCode, // Keep for backward compatibility
            'payment_method_id' => $paymentMethod->id,
            'pos_code' => $posCode, // 4-digit code for cash POS processing
            'pickup_code' => $pickupCode,
            'items_snapshot' => array_map(function ($item) {
                return [
                    'product_id' => $item['product_id'] ?? null,
                    'item_type' => $item['item_type'] ?? 'product',
                    'name' => $item['name'] ?? 'Unknown',
                    'quantity' => $item['quantity'] ?? 1,
                    'price' => $item['price'] ?? 0.0,
                    'modifiers' => $item['modifiers'] ?? null,
                    'configuration' => $item['configuration'] ?? null,
                    'note' => $item['note'] ?? null,
                ];
            }, $items),
            'modifiers_snapshot' => array_map(function ($item) {
                return $item['modifiers'] ?? null;
            }, $items),
            'promo_code_id' => $promoCodeModel?->id,
            'promo_discount' => $discount,
        ]);

        // Increment promo code usage count (for guest orders, don't track customer usage)
        if ($promoCodeModel) {
            $promoCodeModel->increment('used_count');
        }

        // Dispatch event for real-time notification
        event(new OrderCreated($order));

        return $order;
    }

    /**
     * Get paginated orders for customer.
     */
    public function getPaginatedForCustomer(int $customerId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::where('customer_id', $customerId)
            ->with(['store', 'promoCode']);

        // Filter by status (default to 'active' if not provided)
        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'past') {
            $query->past();
        } elseif (in_array($status, ['received', 'mixing', 'ready', 'completed', 'cancelled'])) {
            $query->where('status', $status);
        }

        // Filter by payment method
        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Filter by store
        if (isset($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        // Date range filters
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Total range filters
        if (isset($filters['total_min'])) {
            $query->where('total', '>=', $filters['total_min']);
        }
        if (isset($filters['total_max'])) {
            $query->where('total', '<=', $filters['total_max']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validate sort_by
        $allowedSorts = ['created_at', 'total', 'status', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        // Validate sort_order must be 'asc' or 'desc'
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Find order by ID for customer.
     */
    public function findByIdForCustomer(int $id, int $customerId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->with(['store', 'promoCode'])
            ->find($id);
    }

    /**
     * Find active order for customer (most recent active order).
     */
    public function findActiveOrderForCustomer(int $customerId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->active()
            ->with(['store', 'promoCode', 'paymentMethod'])
            ->latest()
            ->first();
    }

    /**
     * Update order status.
     */
    public function updateStatus(Order $order, string $status): bool
    {
        return $order->update(['status' => $status]);
    }
}

