<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\CustomerRedeemWallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RewardResource;
use App\Services\RedeemService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/rewards: the customer's Rewards wallet ("My Rewards").
 * Available gifts first (soonest expiry first), then gifts used or
 * expired in the last 30 days (newest first).
 */
class RewardsController extends Controller
{
    public function __construct(private RedeemService $redeemService) {}

    public function index(): JsonResponse
    {
        $customer = auth('api')->user();
        $this->redeemService->sweepExpired($customer);

        $available = CustomerRedeemWallet::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerRedeemWallet::STATUS_AVAILABLE)
            ->with('redeemItem')
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderByDesc('id')
            ->get();

        $since = now()->subDays(30);
        $past = CustomerRedeemWallet::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                CustomerRedeemWallet::STATUS_APPLIED,
                CustomerRedeemWallet::STATUS_EXPIRED,
            ])
            ->where(function ($q) use ($since) {
                $q->where('used_at', '>=', $since)
                    ->orWhere(fn ($q) => $q->whereNull('used_at')->where('expires_at', '>=', $since))
                    ->orWhere(fn ($q) => $q->whereNull('used_at')->whereNull('expires_at')->where('updated_at', '>=', $since));
            })
            ->with('redeemItem')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return apiSuccess([
            'rewards' => RewardResource::collection($available->concat($past))->resolve(),
        ]);
    }
}
