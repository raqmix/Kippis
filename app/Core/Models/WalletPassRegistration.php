<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletPassRegistration extends Model
{
    protected $table = 'wallet_pass_registrations';

    protected $fillable = [
        'provider',
        'customer_id',
        'device_library_id',
        'pass_type_id',
        'serial_number',
        'push_token',
        'last_updated_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

    public const PROVIDER_APPLE = 'apple';
    public const PROVIDER_GOOGLE = 'google';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
