<?php

namespace App\Filament\Widgets;

use App\Core\Models\Admin;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Admins', Admin::count())
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Active Admins', Admin::where('is_active', true)->count())
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('2FA Enabled', Admin::where('two_factor_enabled', true)->count())
                ->icon('heroicon-o-key')
                ->color('info'),
        ];
    }
}

