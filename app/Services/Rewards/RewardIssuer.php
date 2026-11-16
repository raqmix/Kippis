<?php

namespace App\Services\Rewards;

use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The one way the system puts a gift into a customer's Rewards wallet.
 *
 * Callers: order streak and referral (brief C), birthday (brief D), admin
 * grants. Points redemptions keep going through RedeemService::claim.
 *
 * Idempotent per (customer, source, source_ref): issuing the same streak
 * cycle / referral / birthday year twice returns the existing entry, so
 * jobs and observers can retry safely. Pass a stable source_ref, e.g.
 * "streak:2026-11", "referral:{referredCustomerId}", "birthday:2026".
 * With source_ref null there is no dedupe (every call creates one).
 */
class RewardIssuer
{
    /**
     * @param  Customer|int  $customer
     * @param  string  $source       CustomerRedeemWallet::SOURCE_* (streak|referral|birthday|admin|redeem)
     * @param  string  $rewardType   CustomerRedeemWallet::TYPE_* (product|free_item|counter)
     * @param  array  $config        free_item: ['category_ids' => int[], 'product_ids' => int[]?, 'max_value' => float]
     *                               product:   ['product_id' => int]
     *                               counter:   anything you want to show the cashier, e.g. ['note' => '...']
     * @param  array  $title         ['en' => '...', 'ar' => '...']
     * @param  int|null  $ttlDays    days until expiry; null = rewards.wallet_ttl_days (30); 0 = never
     * @param  string|null  $sourceRef  idempotency key within (customer, source)
     * @param  CarbonInterface|null  $expiresAt  explicit expiry, wins over $ttlDays (e.g. end of birthday month)
     */
    public function issue(
        Customer|int $customer,
        string $source,
        string $rewardType,
        array $config,
        array $title,
        ?int $ttlDays = null,
        ?string $sourceRef = null,
        ?CarbonInterface $expiresAt = null,
    ): CustomerRedeemWallet {
        $customerId = $customer instanceof Customer ? (int) $customer->getKey() : (int) $customer;

        if (! in_array($source, CustomerRedeemWallet::SOURCES, true)) {
            throw new \InvalidArgumentException("Unknown reward source: {$source}");
        }
        if (! in_array($rewardType, CustomerRedeemWallet::REWARD_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown reward type: {$rewardType}");
        }
        if ($rewardType === CustomerRedeemWallet::TYPE_PRODUCT && empty($config['product_id'])) {
            throw new \InvalidArgumentException('A product reward needs reward_config.product_id.');
        }

        if ($sourceRef !== null && ($existing = $this->find($customerId, $source, $sourceRef))) {
            return $existing;
        }

        if ($expiresAt === null) {
            $ttlDays ??= (int) Setting::get('rewards.wallet_ttl_days', 30);
            $expiresAt = $ttlDays > 0 ? now()->addDays($ttlDays) : null;
        }

        try {
            return CustomerRedeemWallet::create([
                'customer_id'         => $customerId,
                'redeem_item_id'      => null,
                'source'              => $source,
                'source_ref'          => $sourceRef,
                'reward_type'         => $rewardType,
                'reward_config'       => $config,
                'points_spent'        => 0,
                'status'              => CustomerRedeemWallet::STATUS_AVAILABLE,
                'expires_at'          => $expiresAt,
                'title_snapshot_json' => array_filter([
                    'en' => $title['en'] ?? null,
                    'ar' => $title['ar'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ]);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            // Lost a race against a parallel issue for the same key.
            if ($sourceRef !== null && ($existing = $this->find($customerId, $source, $sourceRef))) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * Free drink from the settings presets. $preset is 'free_coffee'
     * (streak/referral) or 'birthday_drink'. Reads
     * rewards.{preset}.category_ids and rewards.{preset}.max_value.
     */
    public function issueFreeItemFromSettings(
        Customer|int $customer,
        string $source,
        string $preset = 'free_coffee',
        ?string $sourceRef = null,
        ?CarbonInterface $expiresAt = null,
        ?int $ttlDays = null,
    ): CustomerRedeemWallet {
        return $this->issue(
            $customer,
            $source,
            CustomerRedeemWallet::TYPE_FREE_ITEM,
            self::freeItemConfig($preset),
            [
                'en' => trans("rewards.{$preset}", [], 'en'),
                'ar' => trans("rewards.{$preset}", [], 'ar'),
            ],
            $ttlDays,
            $sourceRef,
            $expiresAt,
        );
    }

    /** reward_config for a settings preset (free_coffee | birthday_drink). */
    public static function freeItemConfig(string $preset): array
    {
        $defaults = ['free_coffee' => 130, 'birthday_drink' => 170];

        $ids = Setting::get("rewards.{$preset}.category_ids", []);
        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?? array_filter(array_map('trim', explode(',', $ids)));
        }

        return [
            'category_ids' => array_values(array_map('intval', (array) $ids)),
            'max_value'    => (float) Setting::get("rewards.{$preset}.max_value", $defaults[$preset] ?? 0),
        ];
    }

    private function find(int $customerId, string $source, string $sourceRef): ?CustomerRedeemWallet
    {
        return CustomerRedeemWallet::query()
            ->where('customer_id', $customerId)
            ->where('source', $source)
            ->where('source_ref', $sourceRef)
            ->first();
    }
}
