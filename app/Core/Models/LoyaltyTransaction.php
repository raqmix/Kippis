<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Database\Factories\LoyaltyTransactionFactory;

class LoyaltyTransaction extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return LoyaltyTransactionFactory::new();
    }

    public const TYPE_EARNED = 'earned';
    public const TYPE_REDEEMED = 'redeemed';
    public const TYPE_ADJUSTED = 'adjusted';
    public const TYPE_EXPIRED = 'expired';
    public const TYPE_DEDUCTED = 'deducted';
    /** Bookkeeping rows written by `loyalty:rescale` (see LoyaltyRescaler). */
    public const TYPE_RESCALE = 'rescale';

    /** reference_type of the rescale close-out row (points = -old balance). */
    public const REF_RESCALE_CLOSE = 'loyalty_rescale_close';
    /** reference_type of the rescale re-issue rows (new-scale lots). */
    public const REF_RESCALE_LOT = 'loyalty_rescale_lot';

    protected $fillable = [
        'wallet_id',
        'type',
        'points',
        'expires_at',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the wallet that owns this transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(LoyaltyWallet::class, 'wallet_id');
    }

    /**
     * Get the admin who created this transaction (if manual adjustment).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}

