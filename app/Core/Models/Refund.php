<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'order_id',
        'admin_id',
        'type',
        'amount',
        'reason',
        'status',
        'gateway_reference',
        'gateway_response',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
            'processed_at'     => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
