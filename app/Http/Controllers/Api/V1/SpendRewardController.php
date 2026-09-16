<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\CustomerSpendReward;
use App\Core\Models\SpendRewardTier;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SpendRewardResource;
use App\Services\SpendRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing spend-milestone rewards API.
 *
 * @group Spend Rewards
 */
class SpendRewardController extends Controller
{
    public function __construct(private SpendRewardService $service) {}

    /**
     * Progress + active vouchers for the authenticated customer.
     *
     * Response includes:
     *   - lifetime_spent_piasters
     *   - each active tier with the customer's progress toward its
     *     next crossing (percent + remaining EGP)
     *   - active (available/picked/applied) vouchers with full picker
     *     data so the client can render the redeem flow without extra
     *     round-trips.
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Core\Models\Customer|null $customer */
        $customer = auth('api')->user();
        if (! $customer) {
            return apiError('UNAUTHORIZED', 'unauthorized', 401);
        }

        // Suppress "unused $request" hint — kept in the signature so
        // future filter params (?locale, ?since) drop in cleanly.
        unset($request);

        $lifetime = (int) ($customer->lifetime_spent_piasters ?? 0);

        $tiers = SpendRewardTier::active()->orderByDesc('sort_order')->get();
        $tierProgress = $tiers->map(function (SpendRewardTier $t) use ($lifetime) {
            $threshold = (int) $t->threshold_piasters;
            $nextCross = $t->cycle === 'repeating'
                ? ((intdiv($lifetime, $threshold) + 1) * $threshold)
                : $threshold;
            $remainingPiasters = max(0, $nextCross - $lifetime);
            $percent = $nextCross > 0
                ? min(100, (int) round(($lifetime % max($threshold, 1)) / $threshold * 100))
                : 0;
            // For 'once' tiers already crossed, show 100%.
            if ($t->cycle === 'once' && $lifetime >= $threshold) {
                $percent = 100;
                $remainingPiasters = 0;
            }
            return [
                'id' => $t->id,
                'name_en' => $t->name_en,
                'name_ar' => $t->name_ar,
                'description_en' => $t->description_en,
                'description_ar' => $t->description_ar,
                'threshold_piasters' => $threshold,
                'cycle' => $t->cycle,
                'progress_percent' => $percent,
                'remaining_piasters' => $remainingPiasters,
            ];
        });

        $vouchers = $customer->spendRewards()
            ->active()
            ->with('tier')
            ->orderByDesc('issued_at')
            ->get();

        return apiSuccess([
            'lifetime_spent_piasters' => $lifetime,
            'tiers' => $tierProgress,
            'vouchers' => SpendRewardResource::collection($vouchers),
        ]);
    }

    /**
     * Set the customer's picks for a voucher (product per choice group).
     * Body: {"picks": [{"label_en": "Main", "product_id": 42}, ...]}
     *
     * @authenticated
     */
    public function pick(Request $request, int $id): JsonResponse
    {
        /** @var \App\Core\Models\Customer|null $customer */
        $customer = auth('api')->user();
        if (! $customer) return apiError('UNAUTHORIZED', 'unauthorized', 401);

        $data = $request->validate([
            'picks' => 'required|array|min:1',
            'picks.*.product_id' => 'required|integer',
        ]);

        $reward = CustomerSpendReward::where('id', $id)
            ->where('customer_id', $customer->id)
            ->first();
        if (! $reward) return apiError('NOT_FOUND', 'spend_reward_not_found', 404);

        try {
            $reward = $this->service->setPicks($reward, $data['picks']);
        } catch (\DomainException $e) {
            return apiError('INVALID_PICKS', $e->getMessage(), 422);
        }

        return apiSuccess((new SpendRewardResource($reward->load('tier')))->toArray($request));
    }

    // v1 does not expose apply-to-cart — the customer picks their
    // items and shows the resulting voucher at pickup, staff fulfills
    // manually. See SpendRewardService for the v2 auto-injection plan.
}
