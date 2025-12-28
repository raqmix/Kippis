<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
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
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /**
     * Add points to the wallet.
     */
    public function addPoints(int $points, string $type = 'earned', ?string $description = null, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null): LoyaltyTransaction
    {
        $this->increment('points', $points);

        return $this->transactions()->create([
            'type' => $type,
            'points' => $points,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Deduct points from the wallet.
     */
    public function deductPoints(int $points, string $type = 'redeemed', ?string $description = null, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null): LoyaltyTransaction
    {
        $this->decrement('points', $points);

        return $this->transactions()->create([
            'type' => $type,
            'points' => -$points,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy,
        ]);
    }
}

