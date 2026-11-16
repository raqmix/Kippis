<?php

namespace App\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "Double points" rule (Happy Hour, Monday Refill). Evaluated against the
 * order's created_at in the business timezone (Africa/Cairo) when the
 * order completes; the highest matching multiplier wins (no stacking).
 *
 * Examples: Monday all day x2 => days_of_week [1]; daily 15:00-18:00 x2
 * => time_from 15:00, time_to 18:00. time_to is exclusive; a window with
 * time_from after time_to runs past midnight. starts_at / ends_at are
 * inclusive Cairo calendar dates.
 */
class PointsMultiplierRule extends Model
{
    protected $fillable = [
        'name', 'multiplier', 'days_of_week', 'time_from', 'time_to',
        'starts_at', 'ends_at', 'platforms', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'multiplier'   => 'decimal:2',
            'days_of_week' => 'array',
            'platforms'    => 'array',
            'starts_at'    => 'date:Y-m-d',
            'ends_at'      => 'date:Y-m-d',
            'is_active'    => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Does this rule cover $at (any timezone; converted to Cairo) on $platform? */
    public function matches(CarbonInterface $at, ?string $platform = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $local = $at->copy()->setTimezone(config('core.business_timezone', 'Africa/Cairo'));

        $days = array_map('intval', (array) $this->days_of_week);
        if ($days && ! in_array($local->dayOfWeekIso, $days, true)) {
            return false;
        }

        $date = $local->toDateString();
        if ($this->starts_at && $date < $this->starts_at->toDateString()) {
            return false;
        }
        if ($this->ends_at && $date > $this->ends_at->toDateString()) {
            return false;
        }

        $platforms = array_filter((array) $this->platforms);
        if ($platforms && ! in_array($platform, $platforms, true)) {
            return false;
        }

        $from = $this->normalizeTime($this->time_from);
        $to = $this->normalizeTime($this->time_to);
        if ($from !== null || $to !== null) {
            $now = $local->format('H:i:s');
            $from ??= '00:00:00';
            $to ??= '24:00:00';
            $inside = $from <= $to
                ? ($now >= $from && $now < $to)
                : ($now >= $from || $now < $to);
            if (! $inside) {
                return false;
            }
        }

        return true;
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;

        return strlen($value) === 5 ? $value . ':00' : $value;
    }
}
