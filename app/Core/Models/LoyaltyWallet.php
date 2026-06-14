<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Database\Factories\LoyaltyWalletFactory;

class LoyaltyWallet extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return LoyaltyWalletFactory::new();
    }

    protected $fillable = [
        'customer_id',
        'points',
        'qr_token',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Assign a non-enumerable reorder token so the kiosk can identify a
        // wallet by scanned QR without exposing the sequential primary key.
        static::creating(function (LoyaltyWallet $wallet) {
            if (empty($wallet->qr_token)) {
                $wallet->qr_token = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Get the customer that owns this wallet.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'wallet_id');
    }

    /**
     * Add points to the wallet.
     *
     * Wrapped in a transaction with a row lock so concurrent earns/deducts
     * (refund racing a redeem, double-fire from event replay, etc.) can't
     * interleave between the balance write and the transaction-log insert.
     */
    public function addPoints(int $points, string $type = 'earned', ?string $description = null, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null): LoyaltyTransaction
    {
        return DB::transaction(function () use ($points, $type, $description, $referenceType, $referenceId, $createdBy) {
            $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $locked->increment('points', $points);

            $transaction = $locked->transactions()->create([
                'type' => $type,
                'points' => $points,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy,
            ]);

            // Fan out the wallet-state change to Apple Wallet (APNs push)
            // and Google Wallet (LoyaltyObject.patch) so installed passes
            // refresh within the §7 60s SLA. Each provider is independently
            // feature-flagged so this is a no-op until creds land.
            \App\Events\LoyaltyWalletUpdated::dispatch($locked->refresh(), $type);

            // Keep the in-memory instance in sync with the row we wrote.
            $this->setRawAttributes($locked->getAttributes(), true);

            return $transaction;
        });
    }

    /**
     * Deduct points from the wallet. Throws \DomainException when the
     * balance can't cover the deduction so refunds racing redeems can't
     * drive the wallet negative (#18).
     */
    public function deductPoints(int $points, string $type = 'redeemed', ?string $description = null, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null): LoyaltyTransaction
    {
        return DB::transaction(function () use ($points, $type, $description, $referenceType, $referenceId, $createdBy) {
            $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->points < $points) {
                throw new \DomainException("Insufficient loyalty balance: have {$locked->points}, need {$points}.");
            }

            $locked->decrement('points', $points);

            $transaction = $locked->transactions()->create([
                'type' => $type,
                'points' => -$points,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy,
            ]);

            \App\Events\LoyaltyWalletUpdated::dispatch($locked->refresh(), $type);

            $this->setRawAttributes($locked->getAttributes(), true);

            return $transaction;
        });
    }
}

