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

// Stop-gap queue processor. Plesk doesn't give us root + supervisor
// access here, so we can't run `queue:work` as a daemon. Instead we
// piggyback on the existing per-minute `schedule:run` cron: each tick
// drains the queue once, then exits, so jobs (Foodics order push,
// Apple/Google Wallet pushes, etc.) get picked up within ~60s
// instead of rotting forever in the jobs table.
// --max-time=50 leaves headroom under the 60s schedule window so two
// runs don't race; withoutOverlapping is a belt-and-braces lock.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50 --sleep=0')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

// Prune old analytics, anonymize archived orders, hard-delete expired customers — daily at 02:00
Schedule::command('data:prune')->dailyAt('02:00')->withoutOverlapping()->runInBackground();

// Foodics order status — poll fallback every 5 minutes. Webhook is the
// primary path; this catches any missed / dropped events.
Schedule::command('foodics:sync-order-status')->everyFiveMinutes()->withoutOverlapping();

// Foodics catalog — daily at 03:00, refresh categories → products → modifiers.
Schedule::call(function (): void {
    $sync = app(FoodicsSyncService::class);
    try {
        $cats = $sync->syncCategories();
        $prods = $sync->syncProducts();
        $mods = $sync->syncModifiers();
        Log::info('FOODICS_CATALOG_SYNC_RUN', [
            'categories' => $cats,
            'products' => $prods,
            'modifiers' => $mods,
        ]);
    } catch (\Throwable $e) {
        Log::error('FOODICS_CATALOG_SYNC_FAILED', ['error' => $e->getMessage()]);
    }
})->dailyAt('03:00')->name('foodics:sync-catalog')->withoutOverlapping();
