<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\PromoCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OfferResource;
use Illuminate\Http\JsonResponse;

/**
 * Public "Offers" surface — the customer-facing list of active promo
 * codes that ops has opted in to advertise.
 *
 * Distinct from the existing GET /v1/promotions endpoint, which returns
 * banner images (Promotion model). This one returns actionable discount
 * offers (PromoCode with visible_to_customer=true) so the Flutter Offers
 * tab and the web /offers page can show real deals with real terms.
 *
 * @group Offers
 */
class OfferController extends Controller
{
    /**
     * List browsable offers.
     *
     * Filters: active + within the valid_from/valid_to window + under
     * the usage cap + `visible_to_customer=true`. Returned ordered by
     * `priority` desc, then most-recent first — so the ops team can
     * control what appears at the top.
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 12,
     *       "code": "BREAKFAST20",
     *       "auto_apply": false,
     *       "name": {"en": "20% off breakfast", "ar": "خصم ٢٠٪ على الإفطار"},
     *       "description": {"en": "...", "ar": "..."},
     *       "discount_summary": "20% off",
     *       "discount_type": "percentage",
     *       "discount_value": 20,
     *       "minimum_order_amount": 50,
     *       "valid_from": "2026-07-01T00:00:00+00:00",
     *       "valid_to": "2026-08-01T00:00:00+00:00"
     *     }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $offers = PromoCode::query()
            ->valid()
            ->where('visible_to_customer', true)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        return apiSuccess(OfferResource::collection($offers));
    }
}
