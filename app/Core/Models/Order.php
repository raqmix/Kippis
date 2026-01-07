<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\OrderFactory;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return OrderFactory::new();
    }

    protected $fillable = [
        'store_id',
        'customer_id',
        'status',
        'total',
        'subtotal',
        'tax',
        'discount',
        'payment_method',
        'payment_method_id',
        'pos_code',
        'pickup_code',
        'items_snapshot',
        'modifiers_snapshot',
        'promo_code_id',
        'promo_discount',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'promo_discount' => 'decimal:2',
            'items_snapshot' => 'array',
            'modifiers_snapshot' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the store that owns this order.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the customer who placed this order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the promo code used in this order.
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * Get the payment method used in this order.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Scope: Active orders (not completed or cancelled).
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Scope: Past orders (completed or cancelled).
     */
    public function scopePast($query)
    {
        return $query->whereIn('status', ['completed', 'cancelled']);
    }

    /**
     * Scope: By status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}

