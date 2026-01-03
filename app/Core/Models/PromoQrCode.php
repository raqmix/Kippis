<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoQrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'points',
        'is_active',
        'available_from',
        'expires_at',
        'max_uses_per_customer',
        'max_total_uses',
        'total_uses_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_active' => 'boolean',
            'available_from' => 'datetime',
            'expires_at' => 'datetime',
            'max_uses_per_customer' => 'integer',
            'max_total_uses' => 'integer',
            'total_uses_count' => 'integer',
        ];
    }

    /**
     * Get all scans for this QR code.
     */
    public function scans(): HasMany
    {
        return $this->hasMany(PromoQrCodeScan::class);
    }

    /**
     * Get the admin who created this QR code.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Check if QR code is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if QR code is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if QR code is currently valid (active, not expired, and within date range).
     */
    public function isValid(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if (now()->isBefore($this->available_from)) {
            return false;
        }

        // Check if max total uses exceeded
        if ($this->max_total_uses !== null && $this->total_uses_count >= $this->max_total_uses) {
            return false;
        }

        return true;
    }

    /**
     * Check if a customer can use this QR code.
     */
    public function canBeUsedByCustomer(int $customerId): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // Check per-customer limit
        if ($this->max_uses_per_customer !== null) {
            $customerUsageCount = $this->scans()
                ->where('customer_id', $customerId)
                ->count();

            if ($customerUsageCount >= $this->max_uses_per_customer) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope: Active QR codes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Valid QR codes (active, not expired, within date range).
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where('available_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: Expired QR codes.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}

