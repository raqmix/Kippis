<?php

namespace App\Filament\Widgets;

use App\Core\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChartWidget extends ChartWidget
{
    public function getHeading(): ?string
    {
        return 'Orders by Status';
    }
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $statuses = ['received', 'mixing', 'ready', 'completed', 'cancelled'];
        $statusLabels = [
            'received' => 'Received',
            'mixing' => 'Mixing',
            'ready' => 'Ready',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
        
        $colors = [
            'received' => 'rgb(59, 130, 246)',    // Blue
            'mixing' => 'rgb(251, 191, 36)',      // Yellow
            'ready' => 'rgb(139, 92, 246)',       // Purple
            'completed' => 'rgb(34, 197, 94)',    // Green
            'cancelled' => 'rgb(239, 68, 68)',    // Red
        ];
        
        // Single query to get all status counts
        $statusCounts = Order::selectRaw('status, COUNT(*) as count')
            ->whereIn('status', $statuses)
            ->groupBy('status')
            ->get()
            ->keyBy('status');
        
        $data = [];
        $backgroundColors = [];
        $labels = [];
        
        foreach ($statuses as $status) {
            $count = $statusCounts->get($status)?->count ?? 0;
            if ($count > 0) {
                $data[] = $count;
                $labels[] = $statusLabels[$status];
                $backgroundColors[] = $colors[$status];
            }
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => array_map(function($color) {
                        return str_replace('rgb', 'rgba', $color) . ', 1)';
                    }, $backgroundColors),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '60%',
        ];
    }
}

