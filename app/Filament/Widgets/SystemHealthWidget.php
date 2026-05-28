<?php

namespace App\Filament\Widgets;

use App\Support\Heartbeat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Heartbeat indicator for the two cron-driven processes that must stay alive:
 *   - Laravel scheduler (`schedule:run` every minute)
 *   - Queue worker     (`queue:work --stop-when-empty` every minute)
 *
 * "Stale" thresholds account for the worst-case gap: cron fires every minute,
 * and the worker can spend up to ~55s on one job before re-touching the
 * heartbeat — so anything older than 120s indicates the process isn't running.
 */
class SystemHealthWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            $this->processStat('Scheduler', 'scheduler', maxAgeSeconds: 120),
            $this->processStat('Queue worker', 'queue', maxAgeSeconds: 120),
            $this->queueDepthStat(),
            $this->failedJobsStat(),
        ];
    }

    private function processStat(string $label, string $channel, int $maxAgeSeconds): Stat
    {
        $last = Heartbeat::lastSeen($channel);
        $age = Heartbeat::ageSeconds($channel);

        if ($last === null) {
            return Stat::make($label, 'Never')
                ->description('No heartbeat received yet')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->icon('heroicon-o-signal-slash');
        }

        $healthy = $age !== null && $age <= $maxAgeSeconds;

        return Stat::make($label, $healthy ? 'Healthy' : 'Stale')
            ->description('Last seen ' . $last->diffForHumans())
            ->descriptionIcon($healthy ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
            ->color($healthy ? 'success' : 'danger')
            ->icon($healthy ? 'heroicon-o-signal' : 'heroicon-o-signal-slash');
    }

    private function queueDepthStat(): Stat
    {
        $pending = DB::table('jobs')->count();

        return Stat::make('Pending jobs', (string) $pending)
            ->description($pending === 0 ? 'Queue empty' : 'Waiting to process')
            ->descriptionIcon('heroicon-m-queue-list')
            ->color($pending > 50 ? 'warning' : 'info')
            ->icon('heroicon-o-queue-list');
    }

    private function failedJobsStat(): Stat
    {
        $failed = DB::table('failed_jobs')->count();

        return Stat::make('Failed jobs', (string) $failed)
            ->description($failed === 0 ? 'No failures' : 'Investigate failures')
            ->descriptionIcon($failed === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
            ->color($failed === 0 ? 'success' : 'danger')
            ->icon('heroicon-o-exclamation-triangle');
    }

    public static function canView(): bool
    {
        // Prevent auto-discovery; rendered explicitly from Dashboard::getHeaderWidgets().
        return false;
    }
}
