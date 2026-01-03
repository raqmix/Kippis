<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoQrCodeScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'promo_qr_code_id',
        'customer_id',
        'points_awarded',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'points_awarded' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * Get the QR code that was scanned.
     */
    public function promoQrCode(): BelongsTo
    {
        return $this->belongsTo(PromoQrCode::class);
    }

    /**
     * Get the customer who scanned the QR code.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

