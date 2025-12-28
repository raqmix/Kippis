<?php

namespace App\Core\Repositories;

use App\Core\Models\Order;
use App\Core\Models\PromoCodeUsage;
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

        return $order;
    }

    /**
     * Get paginated orders for customer.
     */
    public function getPaginatedForCustomer(int $customerId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::where('customer_id', $customerId)
            ->with(['store', 'promoCode']);

        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->active();
            } elseif ($filters['status'] === 'past') {
                $query->past();
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
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

