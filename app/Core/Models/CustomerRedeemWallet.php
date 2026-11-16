<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a customer's Rewards wallet: either an item bought with
 * points (source=redeem) or a gift issued by the system (streak, referral,
 * birthday, admin) via App\Services\Rewards\RewardIssuer.
 *
 * reward_type decides how it is used:
 *   product    applied in the app cart; takes the linked product's price off
 *   free_item  applied in the app cart; takes the most expensive line in
 *              reward_config.category_ids off, capped at reward_config.max_value
 *   counter    not usable in the app cart; the app shows `code` as a QR and the
 *              cashier marks it used (Filament > Redeem gift)
 */
class CustomerRedeemWallet extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_APPLIED   = 'applied';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_REFUNDED  = 'refunded';

    public const SOURCE_REDEEM   = 'redeem';
    public const SOURCE_STREAK   = 'streak';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_BIRTHDAY = 'birthday';
    public const SOURCE_ADMIN    = 'admin';

    public const SOURCES = [
        self::SOURCE_REDEEM, self::SOURCE_STREAK, self::SOURCE_REFERRAL,
        self::SOURCE_BIRTHDAY, self::SOURCE_ADMIN,
    ];

    public const TYPE_PRODUCT   = 'product';
    public const TYPE_FREE_ITEM = 'free_item';
    public const TYPE_COUNTER   = 'counter';

    public const REWARD_TYPES = [self::TYPE_PRODUCT, self::TYPE_FREE_ITEM, self::TYPE_COUNTER];

    /** Unambiguous characters only (no 0/O, 1/I) so cashiers can type it. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 8;

    protected $table = 'customer_redeem_wallet';

    protected $fillable = [
        'customer_id',
        'redeem_item_id',
        'source',
        'source_ref',
        'reward_type',
        'reward_config',
        'code',
        'points_spent',
        'status',
        'expires_at',
        'used_order_id',
        'used_at',
        'used_by_admin_id',
        'title_snapshot_json',
    ];

    protected $attributes = [
        'source'       => self::SOURCE_REDEEM,
        'reward_type'  => self::TYPE_PRODUCT,
        'points_spent' => 0,
        'status'       => self::STATUS_AVAILABLE,
    ];

    protected function casts(): array
    {
        return [
            'points_spent'         => 'integer',
            'expires_at'           => 'datetime',
            'used_at'              => 'datetime',
            'title_snapshot_json'  => 'array',
            'reward_config'        => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            if (empty($entry->code)) {
                $entry->code = static::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Normalise what a cashier typed or scanned: a bare code, lower-case,
     * with spaces/dashes, or the full redeem URL (…/admin/redeem-gift?code=X).
     */
    public static function normaliseCode(?string $input): string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return '';
        }
        if (str_contains($input, '?') || str_contains($input, '://')) {
            $query = parse_url($input, PHP_URL_QUERY);
            if (is_string($query)) {
                parse_str($query, $params);
                if (! empty($params['code']) && is_string($params['code'])) {
                    $input = $params['code'];
                }
            }
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    public function scopeForCode(Builder $query, ?string $input): Builder
    {
        return $query->where('code', static::normaliseCode($input));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function redeemItem(): BelongsTo
    {
        return $this->belongsTo(RedeemItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'used_order_id');
    }

    public function usedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'used_by_admin_id');
    }

    public function getTitle(string $locale = 'en'): string
    {
        $snap = $this->title_snapshot_json ?? [];
        return (string) ($snap[$locale] ?? $snap['en'] ?? $snap['ar']
            ?? $this->redeemItem?->getTitle($locale)
            ?? '');
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    /** Can this entry be applied to an app cart (vs. shown as a QR at the counter)? */
    public function isUsableInApp(): bool
    {
        return in_array($this->reward_type ?? self::TYPE_PRODUCT, [self::TYPE_PRODUCT, self::TYPE_FREE_ITEM], true);
    }

    /**
     * Status as the customer sees it: an `available` row whose expiry
     * passed reads as `expired` even before the sweep updates it.
     */
    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_AVAILABLE && $this->expires_at && $this->expires_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return (string) $this->status;
    }

    /** What the app encodes in the QR: the admin redeem URL with the code. */
    public function qrPayload(): string
    {
        return rtrim((string) config('app.url'), '/') . '/admin/redeem-gift?code=' . urlencode((string) $this->code);
    }
}
