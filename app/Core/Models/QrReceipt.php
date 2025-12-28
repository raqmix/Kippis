<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'store_id',
        'receipt_number',
        'receipt_image',
        'amount',
        'status',
        'points_awarded',
        'points_requested',
        'meta',
        'scanned_at',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'points_awarded' => 'integer',
            'points_requested' => 'integer',
            'meta' => 'array',
            'scanned_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the customer who submitted this receipt.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the store from the receipt (if available).
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the admin who approved this receipt.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Scope: Pending receipts.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Approved receipts.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Rejected receipts.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}

