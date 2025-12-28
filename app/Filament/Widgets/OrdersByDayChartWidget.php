<?php

namespace App\Filament\Widgets;

use App\Core\Models\Order;
use Filament\Widgets\ChartWidget;

class OrdersByDayChartWidget extends ChartWidget
{
    public function getHeading(): ?string
    {
        return 'Orders by Day of Week (Last 4 Weeks)';
    }
    
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = now()->subWeeks(4)->startOfDay();
        
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayData = array_fill(0, 7, 0);
        
        // Use DB aggregation instead of loading all orders
        $dayStats = Order::where('created_at', '>=', $startDate)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DAYOFWEEK(created_at) as day_of_week, COUNT(*) as count')
            ->groupBy('day_of_week')
            ->get();
        
        // MySQL DAYOFWEEK returns 1-7 (1 = Sunday, 7 = Saturday)
        // Convert to 0-6 index (0 = Sunday, 6 = Saturday)
        foreach ($dayStats as $stat) {
            $dayIndex = ($stat->day_of_week - 1) % 7; // Convert 1-7 to 0-6
            $dayData[$dayIndex] = $stat->count;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $dayData,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.8)',
                    'borderColor' => 'rgb(99, 102, 241)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $dayNames,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}

