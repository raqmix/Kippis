<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SquadMember extends Model
{
    protected $fillable = [
        'squad_session_id', 'customer_id', 'nickname', 'joined_at', 'is_host',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'is_host'   => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SquadSession::class, 'squad_session_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(SquadCartItem::class);
    }
}
