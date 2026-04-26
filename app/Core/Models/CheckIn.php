<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    protected $fillable = [
        'customer_id',
        'checked_in_at',
        'streak_count',
        'points_awarded',
        'reward_type',
        'reward_detail',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'date',
            'reward_detail' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
