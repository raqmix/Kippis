<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Order;
use App\Http\Controllers\Controller;
use App\Services\OrderRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderRatingController extends Controller
{
    public function __construct(private readonly OrderRatingService $ratingService) {}

    /**
     * POST /api/v1/orders/{order}/rating
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        $data     = $request->validate(['rating' => ['required', 'integer', 'min:1', 'max:5']]);
        $customer = auth('api')->user();

        try {
            $rating = $this->ratingService->rate($order, $customer, $data['rating']);
        } catch (\DomainException $e) {
            return apiError('RATING_NOT_ALLOWED', $e->getMessage(), 409);
        } catch (\RuntimeException $e) {
            return apiError('FEEDBACK_DISABLED', $e->getMessage(), 403);
        }

        return apiSuccess([
            'data' => [
                'rating'        => $rating->rating,
                'points_earned' => $rating->points_awarded,
                'message_en'    => 'Thanks for your feedback!',
                'message_ar'    => 'شكراً على تقييمك!',
            ],
        ]);
    }

    /**
     * GET /api/v1/orders/{order}/rating
     */
    public function show(Order $order): JsonResponse
    {
        $customer = auth('api')->user();

        if ($order->customer_id !== $customer->id) {
            return apiError('FORBIDDEN', 'Access denied.', 403);
        }

        $rating  = $order->rating;
        $canRate = $order->status === 'completed'
            && $rating === null
            && (bool) \App\Core\Models\Setting::get('feedback.enabled', true);

        return apiSuccess([
            'data' => [
                'rating'   => $rating?->rating,
                'can_rate' => $canRate,
            ],
        ]);
    }
}
