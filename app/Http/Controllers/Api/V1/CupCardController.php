<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CupCard\CupCardService;
use Illuminate\Http\JsonResponse;

class CupCardController extends Controller
{
    public function __construct(private CupCardService $service) {}

    /**
     * GET /api/v1/cup-card
     *
     * @response 200 {"success": true, "data": {"enabled": true, "steps": [0,0,30,40,50,60,70,80,100], "stamps": 2, "next_percent": 30, "next_is_free": false, "card_length": 9, "expires_at": "2026-12-16T10:00:00+02:00", "days_left": 24, "category_ids": [3,4], "min_price": 0, "max_value": 0}}
     */
    public function show(): JsonResponse
    {
        return apiSuccess($this->service->status(auth('api')->user()));
    }
}
