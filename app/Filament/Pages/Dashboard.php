<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminStatsWidget;
use App\Filament\Widgets\CustomerStatsWidget;
use App\Filament\Widgets\RecentTicketRepliesWidget;
use App\Filament\Widgets\StoreStatsWidget;
use App\Filament\Widgets\TicketStatsWidget;
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
            StoreStatsWidget::class,
            CustomerStatsWidget::class,
            AdminStatsWidget::class,
            TicketStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            RecentTicketRepliesWidget::class,
        ];
    }
}

