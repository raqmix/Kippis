<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppOffer\AppOffer;
use App\Services\Loyalty\OrderStreakService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/app-config (public). Switches the mobile app reads on launch.
 */
class AppConfigController extends Controller
{
    public function show(AppOffer $appOffer, OrderStreakService $streak): JsonResponse
    {
        $offer = $appOffer->toArray();

        return apiSuccess([
            'app_offer' => [
                'enabled'  => $offer['enabled'],
                'percent'  => $offer['percent'],
                'label_en' => $offer['label_en'],
                'label_ar' => $offer['label_ar'],
            ],
            'order_streak' => [
                'enabled' => $streak->enabled(),
                'length'  => $streak->length(),
            ],
        ]);
    }
}
