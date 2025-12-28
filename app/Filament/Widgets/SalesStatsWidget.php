<?php

namespace App\Filament\Widgets;

use App\Core\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SalesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        $lastMonthEnd = $thisMonth->copy()->subSecond();

        // Optimize: Use conditional aggregation to get multiple stats in fewer queries
        $todayStats = Order::whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as sales')
            ->first();

        $thisMonthStats = Order::where('created_at', '>=', $thisMonth)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as sales')
            ->first();

        $lastMonthSales = Order::whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        $todaySales = $todayStats->sales ?? 0;
        $todayOrders = $todayStats->orders_count ?? 0;
        $thisMonthSales = $thisMonthStats->sales ?? 0;
        $thisMonthOrders = $thisMonthStats->orders_count ?? 0;

        $growth = $lastMonthSales > 0 
            ? (($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100 
            : 0;

        return [
            Stat::make(__('system.today_sales'), number_format($todaySales, 2) . ' SAR')
                ->description($todayOrders . ' ' . __('system.orders_today'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->icon('heroicon-o-currency-dollar'),
            Stat::make(__('system.this_month_sales'), number_format($thisMonthSales, 2) . ' SAR')
                ->description($thisMonthOrders . ' ' . __('system.orders_this_month'))
                ->descriptionIcon($growth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($growth >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-chart-bar'),
            Stat::make(__('system.total_orders'), Order::count())
                ->description(__('system.all_time_orders'))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info')
                ->icon('heroicon-o-shopping-cart'),
        ];
    }
}

