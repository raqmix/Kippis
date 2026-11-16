<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Referral;
use App\Core\Models\Setting;
use App\Core\Repositories\LoyaltyWalletRepository;
use App\Events\ReferralRewardIssued;
use App\Services\AppOffer\AppOffer;
use App\Services\Rewards\RewardIssuer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bring a Friend.
 *
 * Every customer has ONE reusable personal code (customers.referral_code,
 * backfilled from the code they were already sharing). Applying a code
 * creates one referral row per invitee. Legacy per-referral codes
 * (referrals.referral_code) still resolve to their inviter.
 *
 * Reward mode `referral.reward_type`:
 *   points       (default) both sides get points when the code is applied
 *   free_coffee  the row stays `registered`; on the invitee's FIRST completed
 *                app order both get a free coffee and the row turns converted
 *
 * `referral.monthly_cap` limits how many friends one code can bring per month.
 */
class ReferralService
{
    public const MODE_POINTS = 'points';
    public const MODE_FREE_COFFEE = 'free_coffee';

    public function __construct(
        private LoyaltyWalletRepository $walletRepository,
        private RewardIssuer $rewardIssuer,
        private AppOffer $appOffer,
    ) {}

    public function rewardType(): string
    {
        return Setting::get('referral.reward_type', self::MODE_POINTS) === self::MODE_FREE_COFFEE
            ? self::MODE_FREE_COFFEE
            : self::MODE_POINTS;
    }

    /**
     * Return (creating if needed) the referral code of a customer, plus stats.
     * Existing keys keep their meaning; total_conversions and reward_type are new.
     */
    public function getOrCreateCode(Customer $customer): array
    {
        $code = $this->ensureCode($customer);

        $monthCap = (int) Setting::get('referral.monthly_cap', 5);
        $converted = Referral::where('inviter_id', $customer->id)->where('status', 'converted');

        $shareUrl = config('app.url') . '/r/' . $code;

        return [
            'referral_code'          => $code,
            'share_url'              => $shareUrl,
            'share_text'             => $this->rewardType() === 'free_coffee'
                ? "Join Kippis with my code {$code} and we both get a free coffee on your first order! {$shareUrl}"
                : "Join Kippis with my code {$code} and get free points! {$shareUrl}",
            'conversions_this_month' => (clone $converted)
                ->whereBetween('converted_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'total_conversions'      => (clone $converted)->count(),
            'monthly_cap'            => $monthCap,
            'inviter_points_reward'  => (int) Setting::get('referral.inviter_points', 30),
            'invitee_points_reward'  => (int) Setting::get('referral.invitee_points', 30),
            'reward_type'            => $this->rewardType(),
        ];
    }

    /** The customer's personal code; generated on first use. */
    public function ensureCode(Customer $customer): string
    {
        if ($customer->referral_code) {
            return $customer->referral_code;
        }

        // A customer that predates the backfill: keep the code they shared.
        $legacy = Referral::where('inviter_id', $customer->id)
            ->orderByRaw("status = 'pending' and invitee_id is null desc")
            ->orderBy('id')
            ->value('referral_code');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $legacy && $attempt === 0 ? strtoupper($legacy) : $this->generateUniqueCode();
            try {
                $updated = Customer::whereKey($customer->id)->whereNull('referral_code')
                    ->update(['referral_code' => $code]);
            } catch (QueryException) {
                $legacy = null; // taken by someone else: fall through to a fresh code
                continue;
            }
            $customer->refresh();
            if ($updated || $customer->referral_code) {
                return $customer->referral_code;
            }
        }

        throw new \RuntimeException('Could not generate a referral code.');
    }

    /** Inviter for a code: personal code first, then a legacy referral code. */
    public function findInviter(string $code): ?Customer
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $inviter = Customer::where('referral_code', $code)->first();
        if ($inviter) {
            return $inviter;
        }

        $inviterId = Referral::where('referral_code', $code)->orderBy('id')->value('inviter_id');

        return $inviterId ? Customer::find($inviterId) : null;
    }

    /**
     * Apply a referral code during or after registration of a new customer.
     */
    public function applyReferralCode(Customer $invitee, string $code): Referral
    {
        if (! Setting::get('referral.enabled', true)) {
            throw new \RuntimeException('Referral program is currently disabled.');
        }

        $code = strtoupper(trim($code));
        $inviter = $this->findInviter($code);

        if (! $inviter) {
            throw new \DomainException('Invalid referral code.');
        }

        if ($inviter->id === $invitee->id) {
            throw new \DomainException('You cannot use your own referral code.');
        }

        if (Referral::where('invitee_id', $invitee->id)->exists()) {
            throw new \DomainException('A referral code has already been applied to this account.');
        }

        $monthCap = (int) Setting::get('referral.monthly_cap', 5);
        $friendsThisMonth = Referral::where('inviter_id', $inviter->id)
            ->whereNotNull('invitee_id')
            ->whereBetween('registered_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        if ($friendsThisMonth >= $monthCap) {
            throw new \DomainException('This referral code has reached its monthly limit.');
        }

        $pointsMode = $this->rewardType() === self::MODE_POINTS;
        $inviterPoints = $pointsMode ? (int) Setting::get('referral.inviter_points', 30) : 0;
        $inviteePoints = $pointsMode ? (int) Setting::get('referral.invitee_points', 30) : 0;

        $attributes = [
            'inviter_id'     => $inviter->id,
            'invitee_id'     => $invitee->id,
            'referral_code'  => $code,
            'status'         => $pointsMode ? 'converted' : 'registered',
            'registered_at'  => now(),
            'converted_at'   => $pointsMode ? now() : null,
            'inviter_points' => $inviterPoints,
            'invitee_points' => $inviteePoints,
        ];

        $referral = DB::transaction(function () use ($inviter, $code, $attributes) {
            // A legacy open row with this exact code becomes this invitee's row.
            $open = Referral::where('inviter_id', $inviter->id)
                ->where('referral_code', $code)
                ->where('status', 'pending')
                ->whereNull('invitee_id')
                ->lockForUpdate()
                ->first();

            if ($open) {
                $open->update($attributes);

                return $open;
            }

            return Referral::create($attributes);
        });

        if ($inviterPoints > 0) {
            $inviterWallet = $this->walletRepository->getOrCreateForCustomer($inviter->id);
            $this->walletRepository->addPoints(
                $inviterWallet,
                $inviterPoints,
                'referral_reward',
                "Referral reward — friend {$invitee->name} joined",
                'referral',
                $referral->id
            );
        }

        if ($inviteePoints > 0) {
            $inviteeWallet = $this->walletRepository->getOrCreateForCustomer($invitee->id);
            $this->walletRepository->addPoints(
                $inviteeWallet,
                $inviteePoints,
                'referral_welcome',
                'Welcome bonus from referral',
                'referral',
                $referral->id
            );
        }

        return $referral;
    }

    /**
     * Order completion hook (free_coffee mode): the invitee's first completed
     * app order pays out both free coffees, once. Safe to call for every order.
     */
    public function handleCompletedOrder(Order $order): void
    {
        if (! $order->customer_id || ! $this->appOffer->isAppPlatform($order->platform)) {
            return;
        }

        $referral = Referral::where('invitee_id', $order->customer_id)
            ->where('status', 'registered')
            ->first();
        if (! $referral) {
            return;
        }

        $paid = DB::transaction(function () use ($referral) {
            // Claim the row atomically so a repeated call can't pay twice.
            $claimed = Referral::whereKey($referral->id)
                ->where('status', 'registered')
                ->update(['status' => 'converted', 'converted_at' => now(), 'updated_at' => now()]);
            if (! $claimed) {
                return null;
            }

            $inviter = Customer::find($referral->inviter_id);
            $invitee = Customer::find($referral->invitee_id);
            if (! $inviter || ! $invitee) {
                return null;
            }

            $ref = 'referral:' . $invitee->id;
            $this->rewardIssuer->issueFreeItemFromSettings($inviter, 'referral', 'free_coffee', $ref);
            $this->rewardIssuer->issueFreeItemFromSettings($invitee, 'referral', 'free_coffee', $ref);

            return [$inviter, $invitee];
        });

        if ($paid) {
            ReferralRewardIssued::dispatch(...$paid);
        }
    }

    private function generateUniqueCode(): string
    {
        $prefix = Setting::get('referral.code_prefix', 'KIPPIS');
        do {
            $code = strtoupper($prefix . '-' . Str::random(6));
        } while (
            Customer::where('referral_code', $code)->exists()
            || Referral::where('referral_code', $code)->exists()
        );

        return $code;
    }
}
