<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Loyalty\OrderStreakService;
use Illuminate\Http\JsonResponse;

class OrderStreakController extends Controller
{
    public function __construct(private OrderStreakService $service) {}

    /**
     * GET /api/v1/order-streak
     *
     * @response 200 {"success": true, "data": {"enabled": true, "count": 3, "length": 5, "remaining": 2, "ordered_today": false, "at_risk": true}}
     */
    public function show(): JsonResponse
    {
        return apiSuccess($this->service->status(auth('api')->user()));
    }
}
