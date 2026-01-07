<?php

namespace App\Integrations\Foodics\Models;

use Illuminate\Database\Eloquent\Model;

class FoodicsToken extends Model
{
    protected $table = 'foodics_tokens';

    protected $fillable = [
        'mode',
        'access_token',
        'refresh_token',
        'expires_in',
        'expires_at',
        'token_type',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Check if token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->expires_at);
    }

    /**
     * Get the current valid token for a specific mode.
     *
     * @param string|null $mode 'sandbox' or 'live', null to use config default
     * @return self|null
     */
    public static function getCurrent(?string $mode = null): ?self
    {
        $mode = $mode ?? config('foodics.mode', 'live');
        
        $token = self::where('mode', $mode)
            ->latest()
            ->first();

        if (!$token || $token->isExpired()) {
            return null;
        }

        return $token;
    }
}

