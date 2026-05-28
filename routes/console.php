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
