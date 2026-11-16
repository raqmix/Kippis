<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\LoyaltyWalletRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoyaltyWalletResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Loyalty APIs
 */
class LoyaltyController extends Controller
{
    public function __construct(
        private LoyaltyWalletRepository $loyaltyWalletRepository
    ) {
    }

    /**
     * Get customer loyalty wallet
     * 
     * @authenticated
     * 
     * Returns the customer's loyalty points balance and recent transactions.
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "customer_id": 123,
     *     "balance": 500,
     *     "transactions": [
     *       {
     *         "id": 1,
     *         "type": "earned",
     *         "points": 100,
     *         "created_at": "2025-12-21T10:00:00Z"
     *       }
     *     ]
     *   }
     * }
     */
    public function index(): JsonResponse
    {
        $customer = auth('api')->user();
        $wallet = $this->loyaltyWalletRepository->getOrCreateForCustomer($customer->id);

        $wallet->load(['transactions' => function ($query) {
            // Rescale rows are bookkeeping for the 1-point-per-10-EGP switch.
            // App builds up to 1.0.7 show every non-"earned" row with a minus
            // sign, so they would read as lost points: keep them out of history.
            $query->where('type', '!=', \App\Core\Models\LoyaltyTransaction::TYPE_RESCALE)
                ->latest()->limit(10);
        }]);

        return apiSuccess(new LoyaltyWalletResource($wallet));
    }
}
