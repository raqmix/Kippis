<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SquadSession extends Model
{
    protected $fillable = [
        'host_id', 'store_id', 'invite_code', 'status',
        'locked_at', 'order_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_at'  => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'host_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(SquadMember::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(SquadCartItem::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
