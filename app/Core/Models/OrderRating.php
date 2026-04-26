<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRating extends Model
{
    protected $fillable = [
        'order_id',
        'customer_id',
        'rating',
        'points_awarded',
    ];

    protected function casts(): array
    {
        return [
            'rating'         => 'integer',
            'points_awarded' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
