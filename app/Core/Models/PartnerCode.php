<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerCode extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function normalize(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = self::normalize($value);
    }

    /** Active code by (case-insensitive) value, or null. */
    public static function findActive(?string $code): ?self
    {
        $code = self::normalize($code);

        return $code === '' ? null : static::where('code', $code)->where('is_active', true)->first();
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
