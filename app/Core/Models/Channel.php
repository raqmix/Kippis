<?php

namespace App\Core\Models;

use App\Core\Enums\ChannelType;
use App\Core\Traits\EncryptsCredentials;
use App\Core\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use SoftDeletes, EncryptsCredentials, HasActivityLogs;

    protected $fillable = [
        'name',
        'code',
        'type',
        'status',
        'credentials',
        'settings',
        'webhook_url',
        'last_sync_at',
    ];

    protected $casts = [
        'type' => ChannelType::class,
        'settings' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }
}
