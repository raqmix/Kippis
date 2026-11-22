<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cup card ledger row: one per stamp / unstamp / complete / restore (per order) and expire. */
class CupCardEvent extends Model
{
    public const STAMP = 'stamp';
    public const UNSTAMP = 'unstamp';
    public const COMPLETE = 'complete';
    public const EXPIRE = 'expire';
    public const RESTORE = 'restore';

    protected $fillable = ['customer_id', 'order_id', 'type', 'stamps_before', 'stamps_after', 'meta'];

    protected function casts(): array
    {
        return [
            'stamps_before' => 'integer',
            'stamps_after'  => 'integer',
            'meta'          => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
