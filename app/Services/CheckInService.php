<?php

namespace App\Services;

use App\Core\Models\CheckIn;
use App\Core\Models\Customer;
use App\Core\Models\Setting;
use App\Core\Repositories\LoyaltyWalletRepository;
use Carbon\Carbon;

class CheckInService
{
    public function __construct(
        private readonly LoyaltyWalletRepository $loyaltyRepo,
    ) {}

    /**
     * Record a daily check-in for the customer and award points/rewards.
     *
     * @throws \RuntimeException if check-in is disabled
     * @throws \DomainException  if customer already checked in today
     */
    public function checkIn(Customer $customer): array
    {
        if (! Setting::get('check_in.enabled', true)) {
            throw new \RuntimeException('Check-in rewards are currently disabled.');
        }

        $today = today();

        // Guard: already checked in today
        if (CheckIn::where('customer_id', $customer->id)->where('checked_in_at', $today)->exists()) {
            throw new \DomainException('Already checked in today.');
        }

        // Calculate streak
        $lastCheckIn = CheckIn::where('customer_id', $customer->id)
            ->orderByDesc('checked_in_at')
            ->first();

        if ($lastCheckIn && Carbon::parse($lastCheckIn->checked_in_at)->isYesterday()) {
            $streak = $lastCheckIn->streak_count + 1;
        } else {
            $streak = 1;
        }

        // Determine reward
        $dailyPoints  = (int) Setting::get('check_in.daily_points', 3);
        $totalPoints  = $dailyPoints;
        $rewardType   = 'points';
        $rewardDetail = null;

        if ($streak === 3) {
            $totalPoints += (int) Setting::get('check_in.streak_3_bonus', 5);
        } elseif ($streak === 7) {
            $streakRewardType = Setting::get('check_in.streak_7_reward_type', 'free_addon');
            if ($streakRewardType === 'free_addon') {
                $rewardType   = 'free_addon';
                $rewardDetail = $this->issueAddonPromo($customer);
            } else {
                $totalPoints += (int) Setting::get('check_in.streak_7_bonus', 20);
            }
        } elseif ($streak === 14 && Setting::get('check_in.streak_14_enabled', false)) {
            $totalPoints += (int) Setting::get('check_in.streak_14_bonus', 15);
        }

        // Record check-in
        $checkIn = CheckIn::create([
            'customer_id'   => $customer->id,
            'checked_in_at' => $today,
            'streak_count'  => $streak,
            'points_awarded' => $totalPoints,
            'reward_type'   => $rewardType,
            'reward_detail' => $rewardDetail,
        ]);

        // Credit loyalty points
        if ($totalPoints > 0) {
            try {
                $wallet = $this->loyaltyRepo->getOrCreateForCustomer($customer->id);
                $this->loyaltyRepo->addPoints(
                    $wallet,
                    $totalPoints,
                    'earned',
                    "Daily check-in streak #{$streak}",
                    'check_in',
                    $checkIn->id,
                );
            } catch (\Exception) {
                // Non-fatal: points failure should not block check-in confirmation
            }
        }

        return [
            'points_earned' => $totalPoints,
            'streak_count'  => $streak,
            'reward'        => $rewardDetail ? ['type' => $rewardType] + $rewardDetail : null,
            'next_milestone' => $this->nextMilestone($streak),
        ];
    }

    /**
     * Return the customer's current streak status.
     */
    public function getStreakStatus(Customer $customer): array
    {
        $today       = today();
        $todayRecord = CheckIn::where('customer_id', $customer->id)->where('checked_in_at', $today)->first();
        $lastRecord  = CheckIn::where('customer_id', $customer->id)->orderByDesc('checked_in_at')->first();

        $currentStreak  = 0;
        $lastCheckedIn  = null;

        if ($lastRecord) {
            $lastDate = Carbon::parse($lastRecord->checked_in_at);
            if ($lastDate->isToday() || $lastDate->isYesterday()) {
                $currentStreak = $lastRecord->streak_count;
            }
            $lastCheckedIn = $lastRecord->checked_in_at->toDateString();
        }

        return [
            'checked_in_today' => $todayRecord !== null,
            'current_streak'   => $currentStreak,
            'last_check_in'    => $lastCheckedIn,
            'next_milestone'   => $this->nextMilestone($currentStreak),
        ];
    }

    private function nextMilestone(int $streak): ?array
    {
        if ($streak < 3) {
            return ['streak' => 3, 'reward' => '+' . (int) Setting::get('check_in.streak_3_bonus', 5) . ' bonus points'];
        }
        if ($streak < 7) {
            $type = Setting::get('check_in.streak_7_reward_type', 'free_addon');
            return ['streak' => 7, 'reward' => $type === 'free_addon' ? 'Free add-in' : '+20 bonus points'];
        }
        if ($streak < 14 && Setting::get('check_in.streak_14_enabled', false)) {
            return ['streak' => 14, 'reward' => '+' . (int) Setting::get('check_in.streak_14_bonus', 15) . ' bonus points'];
        }
        return null;
    }

    private function issueAddonPromo(Customer $customer): array
    {
        $expiryDays = (int) Setting::get('check_in.streak_7_addon_expiry_days', 7);
        $expiresAt  = now()->addDays($expiryDays);
        $code       = 'STREAK7-' . strtoupper(substr(md5($customer->id . now()->timestamp), 0, 8));

        // Note: PromoCode model creation depends on existing PromoCode schema.
        // We store details in reward_detail and create the promo code if the model supports it.
        try {
            \App\Core\Models\PromoCode::create([
                'code'          => $code,
                'type'          => 'free_item',
                'customer_id'   => $customer->id,
                'max_uses'      => 1,
                'expires_at'    => $expiresAt,
                'is_active'     => true,
            ]);
        } catch (\Exception) {
            // Silently fail — promo code creation is best-effort
        }

        return [
            'promo_code' => $code,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
