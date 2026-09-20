<?php

use App\Core\Services\FoodicsSyncService;
use App\Support\Heartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler liveness ping — surfaces in the Filament system-health widget.
Schedule::call(fn () => Heartbeat::mark('scheduler'))
    ->everyMinute()
    ->name('heartbeat:scheduler');

// Run creator drop lifecycle transitions every minute
Schedule::command('drops:lifecycle')->everyMinute()->withoutOverlapping();

// Near-real-time queue processor. Per-minute scheduler kicks off a
// worker that stays awake polling every 1s for 55s, then exits so the
// next tick can start a fresh one. Effect: any dispatched job (Foodics
// order push, wallet pushes) gets picked up in ~1s instead of the
// prior ~60s ceiling. withoutOverlapping(2) prevents any per-tick race.
Schedule::command('queue:work --tries=3 --max-time=55 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

// Prune old analytics, anonymize archived orders, hard-delete expired customers — daily at 02:00
Schedule::command('data:prune')->dailyAt('02:00')->withoutOverlapping()->runInBackground();

// Loyalty points expire 1 year from gain (configurable via
// Setting::set('loyalty.points_validity_months', N)). Runs after the
// nightly prune so any anonymized customers are already settled.
Schedule::command('loyalty:expire-points')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

// Foodics order status — poll fallback. The webhook is the primary path,
// so this only needs to catch events it dropped. It used to poll 100
// orders one-by-one every 5 minutes (up to 1,200 requests/hour against a
// 90/minute limit); a smaller batch on a longer cadence covers the same
// gap for a fraction of the budget.
Schedule::command('foodics:sync-order-status')->everyTenMinutes()->withoutOverlapping();

// Scheduled push reminders — every 5 minutes, scan the scheduled_pushes
// table and fire any row whose day-of-week + time-of-day window contains
// "now" in the row's own timezone. Idempotent via a unique-index dedup
// on scheduled_push_sends(push_id, window_at), so overlapping runs (or
// the same cron firing twice) can't double-send.
Schedule::command('pushes:fire-due')->everyFiveMinutes()->withoutOverlapping();

// Foodics catalog — every 15 minutes, keep the app catalog in lock-step
// with each store's Foodics menu group (categories, products, modifiers,
// prices, availability). The command holds its own Cache lock (see
// LOCK_TTL_SECONDS) so a slow run can't be trampled by the next tick; a
// run now spaces its calls behind the shared rate limiter, so it takes
// longer in wall-clock time than it used to. The scheduler's
// withoutOverlapping() is a belt-and-braces guard for the same window.
// runInBackground() so this can't block the per-minute queue worker tick.
Schedule::command('foodics:sync-catalog')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->runInBackground();
