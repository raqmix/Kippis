<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\OrderRating;
use App\Core\Models\Setting;
use App\Core\Repositories\LoyaltyWalletRepository;

class OrderRatingService
{
    public function __construct(
        private readonly LoyaltyWalletRepository $loyaltyRepo,
    ) {}

    /**
     * Rate an order and award micro-points.
     *
     * @throws \RuntimeException  if feedback is disabled
     * @throws \DomainException   if order doesn't belong to customer, is not completed, or already rated
     * @throws \InvalidArgumentException  if rating is out of range
     */
    public function rate(Order $order, Customer $customer, int $rating): OrderRating
    {
        if (! Setting::get('feedback.enabled', true)) {
            throw new \RuntimeException('Feedback is currently disabled.');
        }

        if ($order->customer_id !== $customer->id) {
            throw new \DomainException('Order does not belong to this customer.');
        }

        if ($order->status !== 'completed') {
            throw new \DomainException('Only completed orders can be rated.');
        }

        if (OrderRating::where('order_id', $order->id)->exists()) {
            throw new \DomainException('This order has already been rated.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5.');
        }

        $points = (int) Setting::get('feedback.points_per_rating', 5);

        $orderRating = OrderRating::create([
            'order_id'      => $order->id,
            'customer_id'   => $customer->id,
            'rating'        => $rating,
            'points_awarded' => $points,
        ]);

        if ($points > 0) {
            try {
                $wallet = $this->loyaltyRepo->getOrCreateForCustomer($customer->id);
                $this->loyaltyRepo->addPoints(
                    $wallet,
                    $points,
                    'earned',
                    "Order rating for order #{$order->id}",
                    'order_rating',
                    $orderRating->id,
                );
            } catch (\Exception) {
                // Non-fatal
            }
        }

        return $orderRating;
    }
}
