<?php

namespace App\Core\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return AdminFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'locale',
        'allowed_ips',
        'access_start_time',
        'access_end_time',
        'allowed_days',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'failed_login_attempts' => 'integer',
        'allowed_ips' => 'array',
        'allowed_days' => 'array',
        'access_start_time' => 'datetime',
        'access_end_time' => 'datetime',
    ];

    public function setPasswordAttribute($value): void
    {
        // Only hash if the value is not already hashed (bcrypt hashes are 60 chars and start with $2y$)
        if (!empty($value) && strlen($value) === 60 && str_starts_with($value, '$2y$')) {
            // Already hashed, use as is
            $this->attributes['password'] = $value;
        } elseif (!empty($value)) {
            // Hash the password
            $this->attributes['password'] = Hash::make($value);
        }
        
        // Only update password_changed_at if password is actually being set
        if (!empty($value)) {
            $this->attributes['password_changed_at'] = now();
        }
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function lock(int $minutes = 15): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes),
            'failed_login_attempts' => 0,
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');
        
        if ($this->failed_login_attempts >= 5) {
            $this->lock();
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->update(['failed_login_attempts' => 0]);
    }

    public function generateTwoFactorSecret(): string
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        
        $this->update([
            'two_factor_secret' => encrypt($secret),
        ]);
        
        return $secret;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->two_factor_secret ? decrypt($this->two_factor_secret) : null;
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_secret) {
            return false;
        }

        $google2fa = new Google2FA();
        return $google2fa->verifyKey($this->getTwoFactorSecret(), $code);
    }

    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        
        $this->update([
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ]);
        
        return $codes;
    }

    public function useRecoveryCode(string $code): bool
    {
        if (!$this->two_factor_recovery_codes) {
            return false;
        }
        
        try {
            $codes = json_decode(decrypt($this->two_factor_recovery_codes), true);
            
            if (($key = array_search($code, $codes)) !== false) {
                unset($codes[$key]);
                $this->update([
                    'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
                ]);
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        
        return false;
    }

    public function loginHistories()
    {
        return $this->hasMany(AdminLoginHistory::class);
    }

    public function devices()
    {
        return $this->hasMany(AdminDevice::class);
    }

    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class, 'email', 'email');
    }

    public function securityLogs()
    {
        return $this->hasMany(SecurityLog::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Custom admin notifications (legacy system)
     * Use $admin->notifications (from Notifiable trait) for Laravel database notifications
     */
    public function customNotifications()
    {
        return $this->hasMany(AdminNotification::class)->latest();
    }

    public function unreadCustomNotifications()
    {
        return $this->hasMany(AdminNotification::class)->whereNull('read_at')->latest();
    }

    public function unreadCustomNotificationsCount(): int
    {
        return $this->unreadCustomNotifications()->count();
    }

    public function assignedTickets()
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }
}
