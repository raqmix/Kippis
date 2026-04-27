<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'inviter_id', 'invitee_id', 'referral_code',
        'status', 'registered_at', 'converted_at',
        'inviter_points', 'invitee_points',
    ];

    protected function casts(): array
    {
        return [
            'registered_at'  => 'datetime',
            'converted_at'   => 'datetime',
            'inviter_points' => 'integer',
            'invitee_points' => 'integer',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'invitee_id');
    }
}
