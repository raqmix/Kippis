<?php

namespace App\Filament\Widgets;

use App\Core\Models\SupportTicket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketStatsWidget extends BaseWidget
{
    public ?string $filter = null;

    protected function getStats(): array
    {
        $baseQuery = SupportTicket::query();

        if ($this->filter === 'with_customer') {
            $baseQuery->whereNotNull('customer_id');
        } elseif ($this->filter === 'without_customer') {
            $baseQuery->whereNull('customer_id');
        }

        return [
            Stat::make('Open Tickets', (clone $baseQuery)->where('status', 'open')->count())
                ->icon('heroicon-o-ticket')
                ->color('warning'),
            Stat::make('In Progress', (clone $baseQuery)->where('status', 'in_progress')->count())
                ->icon('heroicon-o-arrow-path')
                ->color('info'),
            Stat::make('Closed Tickets', (clone $baseQuery)->where('status', 'closed')->count())
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'all' => 'All Tickets',
            'with_customer' => 'With Customer',
            'without_customer' => 'Without Customer',
        ];
    }
}

