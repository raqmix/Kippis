<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run creator drop lifecycle transitions every minute
Schedule::command('drops:lifecycle')->everyMinute()->withoutOverlapping();

// Prune old analytics, anonymize archived orders, hard-delete expired customers — daily at 02:00
Schedule::command('data:prune')->dailyAt('02:00')->withoutOverlapping()->runInBackground();
