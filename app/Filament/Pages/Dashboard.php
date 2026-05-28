<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CustomerStatsWidget;
use App\Filament\Widgets\OrderStatusChartWidget;
use App\Filament\Widgets\OrdersByDayChartWidget;
use App\Filament\Widgets\OrdersChartWidget;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Filament\Widgets\RecentTicketRepliesWidget;
use App\Filament\Widgets\SalesStatsWidget;
use App\Filament\Widgets\StoreRevenueChartWidget;
use App\Filament\Widgets\StoreStatsWidget;
use App\Filament\Widgets\SystemHealthWidget;
use App\Filament\Widgets\TicketStatsWidget;
use App\Filament\Widgets\TopProductsWidget;
use App\Filament\Widgets\TopStoresWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function getNavigationLabel(): string
    {
        return __('navigation.dashboard');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SystemHealthWidget::class,
            SalesStatsWidget::class,
            StoreStatsWidget::class,
            CustomerStatsWidget::class,
            TicketStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            OrdersChartWidget::class,
            OrderStatusChartWidget::class,
            StoreRevenueChartWidget::class,
            OrdersByDayChartWidget::class,
            RecentOrdersWidget::class,
            TopStoresWidget::class,
            TopProductsWidget::class,
            RecentTicketRepliesWidget::class,
        ];
    }
}

