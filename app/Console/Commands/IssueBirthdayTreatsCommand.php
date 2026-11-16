<?php

namespace App\Console\Commands;

use App\Core\Models\Customer;
use App\Core\Models\Setting;
use App\Events\BirthdayTreatIssued;
use App\Services\Rewards\RewardIssuer;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Birthday treat (app brief item 13). Daily, Cairo morning: every customer
 * whose birthday (month/day) falls in the current ISO week (Mon-Sun, Cairo)
 * gets one birthday drink in the Rewards wallet, expiring Sunday 23:59:59.
 * At most one per calendar year (source_ref birthday:<year>), so reruns and
 * customers who add a birthdate mid-week are handled by the same query.
 * Feb 29 birthdays are celebrated on Feb 28 in non-leap years.
 */
class IssueBirthdayTreatsCommand extends Command
{
    protected $signature = 'rewards:issue-birthday-treats {--date= : Treat this date (Y-m-d, Cairo) as today}';

    protected $description = 'Issue birthday drink rewards to customers whose birthday is this week';

    public function handle(RewardIssuer $issuer): int
    {
        if (! (bool) Setting::get('birthday_treat.enabled', true)) {
            $this->info('Birthday treat is disabled.');

            return self::SUCCESS;
        }

        $tz = config('core.business_timezone', 'Africa/Cairo');
        $today = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), $tz)->startOfDay()
            : CarbonImmutable::now($tz)->startOfDay();
        $weekStart = $today->startOfWeek(CarbonImmutable::MONDAY);
        $expiresAt = $weekStart->addDays(6)->endOfDay()->utc(); // Eloquent stores Carbon as-is, so hand it UTC

        // (month, day) => the calendar date this week on which it is celebrated
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $weekStart->addDays($i);
            $days[$d->format('n-j')] = $d;
            if ($d->month === 2 && $d->day === 28 && ! $d->isLeapYear()) {
                $days['2-29'] = $d;
            }
        }

        $issued = 0;
        Customer::query()
            ->whereNotNull('birthdate')
            ->where(function ($q) use ($days) {
                foreach (array_keys($days) as $key) {
                    [$m, $d] = explode('-', $key);
                    $q->orWhere(fn ($w) => $w->whereMonth('birthdate', $m)->whereDay('birthdate', $d));
                }
            })
            ->chunkById(200, function ($customers) use ($days, $issuer, $expiresAt, &$issued) {
                foreach ($customers as $customer) {
                    $celebrated = $days[$customer->birthdate->format('n-j')] ?? null;
                    if (! $celebrated) {
                        continue;
                    }

                    $reward = $issuer->issueFreeItemFromSettings(
                        $customer,
                        'birthday',
                        'birthday_drink',
                        'birthday:'.$celebrated->year,
                        $expiresAt,
                    );

                    if ($reward->wasRecentlyCreated) {
                        $issued++;
                        BirthdayTreatIssued::dispatch($customer, $reward);
                    }
                }
            });

        $this->info("Issued {$issued} birthday treat(s).");

        return self::SUCCESS;
    }
}
