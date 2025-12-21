<?php

namespace App\Filament\Widgets;

use App\Core\Models\SecurityLog;
use App\Core\Models\LoginAttempt;
use Filament\Widgets\StatsOverviewWidget;

class SecurityStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $last24h = now()->subDay();
        
        return [
            StatsOverviewWidget\Stat::make('Critical Events (24h)', 
                SecurityLog::where('severity', 'critical')
                    ->where('created_at', '>=', $last24h)
                    ->where('resolved', false)
                    ->count())
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            StatsOverviewWidget\Stat::make('Failed Logins (24h)',
                LoginAttempt::where('success', false)
                    ->where('attempted_at', '>=', $last24h)
                    ->count())
                ->icon('heroicon-o-lock-closed')
                ->color('warning'),
        ];
    }
}

