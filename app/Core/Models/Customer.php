<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Customer extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\CustomerFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'country_code',
        'birthdate',
        'password',
        'avatar',
        'foodics_customer_id',
        'is_verified',
        'google_id',
        'google_refresh_token',
        'apple_id',
        'apple_refresh_token',
        'social_avatar',
        'fcm_token',
        'push_marketing_opt_in',
        'lifetime_spent_piasters',
        'is_staff',
        'partner_code_id',
        'partner_code_set_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'is_verified' => 'boolean',
            'is_staff' => 'boolean',
            'partner_code_set_at' => 'datetime',
            'push_marketing_opt_in' => 'boolean',
            'lifetime_spent_piasters' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function partnerCode(): BelongsTo
    {
        return $this->belongsTo(PartnerCode::class);
    }

    /**
     * Tag this customer with a partner code. One per account: the update
     * only lands while partner_code_id is still null, so concurrent calls
     * can't overwrite each other. Returns false when already tagged.
     */
    public function applyPartnerCode(PartnerCode $code): bool
    {
        $updated = static::whereKey($this->getKey())
            ->whereNull('partner_code_id')
            ->update(['partner_code_id' => $code->id, 'partner_code_set_at' => now()]);

        $this->refresh();

        return $updated > 0;
    }

    /**
     * Get all OTPs for this customer.
     */
    public function otps(): HasMany
    {
        return $this->hasMany(CustomerOtp::class);
    }

    /**
     * Get all orders for this customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Spend-milestone vouchers issued to this customer. See
     * SpendRewardService and the customer_spend_rewards table.
     */
    public function spendRewards(): HasMany
    {
        return $this->hasMany(CustomerSpendReward::class);
    }

    /**
     * Get the loyalty wallet for this customer.
     */
    public function loyaltyWallet(): HasOne
    {
        return $this->hasOne(LoyaltyWallet::class);
    }

    /**
     * Get all carts for this customer.
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'is_verified' => $this->is_verified,
        ];
    }

    /**
     * Generate JWT token for the customer.
     *
     * @return string
     */
    public function generateToken(): string
    {
        return \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this);
    }

    /**
     * Mark customer as verified.
     *
     * @return bool
     */
    public function markAsVerified(): bool
    {
        return $this->update(['is_verified' => true]);
    }
}
