<?php

namespace App\Core\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledPush extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'title_en',
        'title_ar',
        'body_en',
        'body_ar',
        'day_of_week',
        'time_of_day',
        'timezone',
        'grace_minutes',
        'deeplink_type',
        'deeplink_id',
        'audience',
        'active',
        'last_sent_at',
        'last_sent_count',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'grace_minutes' => 'integer',
            'active' => 'boolean',
            'last_sent_at' => 'datetime',
            'last_sent_count' => 'integer',
            'deeplink_id' => 'integer',
        ];
    }

    public function sends()
    {
        return $this->hasMany(ScheduledPushSend::class);
    }

    public function createdByAdmin()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * The most recent "target window" datetime (UTC) for this row, given
     * `now`. Rolls the target back to the last day-of-week + time-of-day
     * combination the row expresses.
     *
     * For a Thursday 08:00 Cairo row called at Thursday 09:15 Cairo, this
     * returns Thursday 08:00 Cairo (in UTC). Called at Thursday 07:00
     * Cairo, it returns the previous Thursday's 08:00.
     *
     * For a daily row (day_of_week = null), it returns today's time_of_day
     * if `now >= today's time_of_day`, else yesterday's.
     */
    public function targetWindowFor(CarbonImmutable $now): CarbonImmutable
    {
        // Interpret time_of_day + today in the row's timezone.
        $tz = $this->timezone ?: 'Africa/Cairo';
        $localNow = $now->setTimezone($tz);
        [$hour, $minute] = array_map('intval', explode(':', $this->timeOfDayString()));

        $todaysWindow = $localNow->setTime($hour, $minute, 0);

        // Daily row: today's window if already past it, else yesterday's.
        if ($this->day_of_week === null) {
            $candidate = $localNow->greaterThanOrEqualTo($todaysWindow)
                ? $todaysWindow
                : $todaysWindow->subDay();
            return $candidate->setTimezone('UTC');
        }

        // Weekly row: roll back to the most recent matching day-of-week.
        // Carbon's dayOfWeek is 0=Sun..6=Sat, matching our column.
        $targetDow = (int) $this->day_of_week;
        $daysBack = ($localNow->dayOfWeek - $targetDow + 7) % 7;

        // On the target day but before the time-of-day → the "most recent
        // window" is a week ago at the same time-of-day.
        if ($daysBack === 0 && $localNow->lessThan($todaysWindow)) {
            $daysBack = 7;
        }

        return $todaysWindow->subDays($daysBack)->setTimezone('UTC');
    }

    /**
     * True when `now` sits inside the row's fire window — between the
     * target time and target + grace_minutes. Used by the console command
     * to short-circuit rows whose window has already passed.
     */
    public function isInFireWindow(CarbonImmutable $now): bool
    {
        $window = $this->targetWindowFor($now);
        $graceEnd = $window->addMinutes((int) $this->grace_minutes);
        return $now->betweenIncluded($window, $graceEnd);
    }

    /**
     * Normalise time_of_day to HH:MM regardless of whether Laravel handed
     * it back as "08:00:00" (raw DB) or a Carbon (some driver setups).
     */
    public function timeOfDayString(): string
    {
        $raw = $this->attributes['time_of_day'] ?? '00:00:00';
        return substr($raw, 0, 5);
    }

    /**
     * Human-readable schedule summary used in the Filament table column.
     */
    public function scheduleLabel(): string
    {
        $days = ['Sundays', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays'];
        $day = $this->day_of_week === null ? 'Every day' : ($days[$this->day_of_week] ?? 'Unknown');
        $tzShort = str_replace('Africa/', '', $this->timezone ?? 'Africa/Cairo');
        return sprintf('%s at %s (%s)', $day, $this->timeOfDayString(), $tzShort);
    }
}
