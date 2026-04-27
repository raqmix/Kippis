<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquadCartItem extends Model
{
    protected $fillable = [
        'squad_session_id', 'squad_member_id', 'product_id',
        'product_kind', 'quantity', 'modifiers', 'note', 'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'modifiers'  => 'array',
            'quantity'   => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SquadSession::class, 'squad_session_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(SquadMember::class, 'squad_member_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
