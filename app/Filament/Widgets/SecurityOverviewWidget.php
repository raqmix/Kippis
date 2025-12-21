<?php

namespace App\Filament\Widgets;

use App\Core\Models\SecurityLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SecurityOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $last24h = now()->subDay();
        
        return [
            Stat::make('Security Events (24h)', 
                SecurityLog::where('created_at', '>=', $last24h)->count())
                ->icon('heroicon-o-shield-exclamation')
                ->color('warning'),
            Stat::make('Unresolved Events',
                SecurityLog::where('resolved', false)->count())
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}

