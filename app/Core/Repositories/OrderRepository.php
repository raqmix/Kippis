<?php

namespace App\Core\Repositories;

use App\Core\Models\Order;
use App\Core\Models\PromoCodeUsage;
use App\Events\OrderCreated;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class OrderRepository
{
    /**
     * Create order from cart.
     */
    public function createFromCart(\App\Core\Models\Cart $cart, string $paymentMethod): Order
    {
        $pickupCode = strtoupper(Str::random(6));

        $order = Order::create([
            'store_id' => $cart->store_id,
            'customer_id' => $cart->customer_id,
            'status' => 'received',
            'total' => $cart->total,
            'subtotal' => $cart->subtotal,
            'discount' => $cart->discount,
            'payment_method' => $paymentMethod,
            'pickup_code' => $pickupCode,
            'items_snapshot' => $cart->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->getName(app()->getLocale()),
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'modifiers' => $item->modifiers_snapshot,
                ];
            })->toArray(),
            'modifiers_snapshot' => $cart->items->pluck('modifiers_snapshot')->toArray(),
            'promo_code_id' => $cart->promo_code_id,
            'promo_discount' => $cart->discount,
        ]);

        // Record promo code usage
        if ($cart->promoCode) {
            PromoCodeUsage::create([
                'promo_code_id' => $cart->promoCode->id,
                'customer_id' => $cart->customer_id,
                'order_id' => $order->id,
                'discount_amount' => $cart->discount,
                'used_at' => now(),
            ]);

            $cart->promoCode->increment('used_count');
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
     * Update order status.
     */
    public function updateStatus(Order $order, string $status): bool
    {
        return $order->update(['status' => $status]);
    }
}

