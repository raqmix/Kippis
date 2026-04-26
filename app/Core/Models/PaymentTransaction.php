<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'amount',
        'gateway',
        'gateway_reference',
        'gateway_status',
        'gateway_response',
        'reconciled',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
            'reconciled'       => 'boolean',
            'reconciled_at'    => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
