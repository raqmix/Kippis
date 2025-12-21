<?php

namespace App\Filament\Widgets;

use App\Core\Models\SupportTicket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Open Tickets', SupportTicket::where('status', 'open')->count())
                ->icon('heroicon-o-ticket')
                ->color('warning'),
            Stat::make('In Progress', SupportTicket::where('status', 'in_progress')->count())
                ->icon('heroicon-o-arrow-path')
                ->color('info'),
            Stat::make('Closed Tickets', SupportTicket::where('status', 'closed')->count())
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}

