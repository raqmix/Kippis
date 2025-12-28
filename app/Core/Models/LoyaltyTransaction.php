<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'type',
        'points',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    /**
     * Get the wallet that owns this transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(LoyaltyWallet::class);
    }

    /**
     * Get the admin who created this transaction (if manual adjustment).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}

