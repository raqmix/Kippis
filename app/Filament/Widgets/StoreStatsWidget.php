<?php

namespace App\Filament\Widgets;

use App\Core\Models\Store;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Stores', Store::count())
                ->icon('heroicon-o-building-storefront')
                ->color('primary'),
            Stat::make('Active Stores', Store::where('is_active', true)->count())
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Receiving Online Orders', Store::where('receive_online_orders', true)->count())
                ->icon('heroicon-o-shopping-cart')
                ->color('info'),
        ];
    }
}

