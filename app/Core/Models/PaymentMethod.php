<?php

namespace App\Core\Models;

use App\Core\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class PaymentMethod extends Model
{
    use SoftDeletes, HasActivityLogs;

    protected $fillable = [
        'name',
        'code',
        'channel_id',
        'is_active',
        'configuration',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'configuration' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('payment_methods_v1_list'));
        static::deleted(fn () => Cache::forget('payment_methods_v1_list'));
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
