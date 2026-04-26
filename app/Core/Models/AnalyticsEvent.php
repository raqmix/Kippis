<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null; // Only created_at

    protected $fillable = [
        'event_name',
        'customer_id',
        'store_id',
        'session_id',
        'platform',
        'properties',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'properties'  => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
