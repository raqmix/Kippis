<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\Referral;
use App\Core\Models\Setting;
use App\Core\Repositories\LoyaltyWalletRepository;
use Illuminate\Support\Str;

class ReferralService
{
    private const SETTINGS_GROUP = 'referral';

    public function __construct(private LoyaltyWalletRepository $walletRepository) {}

    /**
     * Return or generate the referral code for a customer.
     */
    public function getOrCreateCode(Customer $customer): array
    {
        $referral = Referral::where('inviter_id', $customer->id)
            ->whereNull('invitee_id')
            ->where('status', 'pending')
            ->first();

        if (! $referral) {
            $referral = Referral::create([
                'inviter_id'    => $customer->id,
                'referral_code' => $this->generateUniqueCode(),
                'status'        => 'pending',
            ]);
        }

        $prefix   = Setting::get('referral.code_prefix', 'KIPPIS');
        $monthCap = (int) Setting::get('referral.monthly_cap', 5);

        // Count conversions this month
        $conversionsThisMonth = Referral::where('inviter_id', $customer->id)
            ->where('status', 'converted')
            ->whereBetween('converted_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $shareUrl = config('app.url') . '/r/' . $referral->referral_code;

        return [
            'referral_code'          => $referral->referral_code,
            'share_url'              => $shareUrl,
            'share_text'             => "Join Kippis with my code {$referral->referral_code} and get free points! {$shareUrl}",
            'conversions_this_month' => $conversionsThisMonth,
            'monthly_cap'            => $monthCap,
            'inviter_points_reward'  => (int) Setting::get('referral.inviter_points', 30),
            'invitee_points_reward'  => (int) Setting::get('referral.invitee_points', 30),
        ];
    }

    /**
     * Apply a referral code during or after registration of a new customer.
     */
    public function applyReferralCode(Customer $invitee, string $code): void
    {
        if (! Setting::get('referral.enabled', true)) {
            throw new \RuntimeException('Referral program is currently disabled.');
        }

        $referral = Referral::where('referral_code', $code)
            ->where('status', 'pending')
            ->whereNull('invitee_id')
            ->first();

        if (! $referral) {
            throw new \DomainException('Invalid or already used referral code.');
        }

        if ($referral->inviter_id === $invitee->id) {
            throw new \DomainException('You cannot use your own referral code.');
        }

        // Check monthly cap
        $monthCap = (int) Setting::get('referral.monthly_cap', 5);
        $conversionsThisMonth = Referral::where('inviter_id', $referral->inviter_id)
            ->where('status', 'converted')
            ->whereBetween('converted_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        if ($conversionsThisMonth >= $monthCap) {
            throw new \DomainException('This referral code has reached its monthly limit.');
        }

        $inviterPoints = (int) Setting::get('referral.inviter_points', 30);
        $inviteePoints = (int) Setting::get('referral.invitee_points', 30);

        $referral->update([
            'invitee_id'     => $invitee->id,
            'status'         => 'converted',
            'registered_at'  => now(),
            'converted_at'   => now(),
            'inviter_points' => $inviterPoints,
            'invitee_points' => $inviteePoints,
        ]);

        // Award points to both parties
        if ($inviterPoints > 0) {
            $inviterWallet = $this->walletRepository->getOrCreateForCustomer($referral->inviter_id);
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
    }

    private function generateUniqueCode(): string
    {
        $prefix = Setting::get('referral.code_prefix', 'KIPPIS');
        do {
            $code = strtoupper($prefix . '-' . Str::random(6));
        } while (Referral::where('referral_code', $code)->exists());

        return $code;
    }
}
