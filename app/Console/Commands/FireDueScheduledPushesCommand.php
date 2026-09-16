<?php

namespace App\Console\Commands;

use App\Services\ScheduledPushService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Fires every 5 minutes (see routes/console.php). Scans active
 * scheduled_pushes rows, finds the ones whose window contains `now` in
 * the row's own timezone, and dispatches queued send-batches for each.
 *
 * Idempotent — the unique index on scheduled_push_sends(push_id,
 * window_at) prevents the same window from being fired twice by
 * overlapping cron runs.
 */
class FireDueScheduledPushesCommand extends Command
{
    protected $signature = 'pushes:fire-due';

    protected $description = 'Fire any scheduled pushes whose window contains "now".';

    public function handle(ScheduledPushService $service): int
    {
        $now = CarbonImmutable::now('UTC');
        $fired = $service->fireAllDueNow($now);

        if (empty($fired)) {
            $this->info('No scheduled pushes are in their fire window right now.');
            return self::SUCCESS;
        }

        foreach ($fired as [$push, $count]) {
            $this->info(sprintf(
                'Fired push #%d "%s" — %d recipients',
                $push->id,
                $push->name,
                $count,
            ));
        }

        return self::SUCCESS;
    }
}
