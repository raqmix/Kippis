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

    public function index(): JsonResponse
    {
        $customer = auth('api')->user();
        $wallet = $this->loyaltyWalletRepository->getOrCreateForCustomer($customer->id);

        $wallet->load(['transactions' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return apiSuccess(new LoyaltyWalletResource($wallet));
    }
}
