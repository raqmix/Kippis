<?php

namespace App\Filament\Widgets;

use App\Core\Models\Order;
use App\Core\Models\Store;
use Filament\Widgets\ChartWidget;

class StoreRevenueChartWidget extends ChartWidget
{
    public function getHeading(): ?string
    {
        return 'Revenue by Store (Last 30 Days)';
    }
    
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = now()->subDays(30)->startOfDay();
        
        // Use a single query with grouping to get store revenue
        $storeRevenues = Order::where('created_at', '>=', $startDate)
            ->where('status', '!=', 'cancelled')
            ->whereHas('store', function ($query) {
                $query->where('is_active', true);
            })
            ->selectRaw('store_id, SUM(total) as revenue')
            ->groupBy('store_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
        
        // Get store details in a single query
        $storeIds = $storeRevenues->pluck('store_id')->toArray();
        $stores = Store::whereIn('id', $storeIds)
            ->get()
            ->keyBy('id');
        
        $labels = [];
        $revenueData = [];
        $colors = [];
        
        // Generate a color palette
        $colorPalette = [
            'rgb(99, 102, 241)',   // Indigo
            'rgb(34, 197, 94)',    // Green
            'rgb(251, 191, 36)',   // Yellow
            'rgb(239, 68, 68)',    // Red
            'rgb(139, 92, 246)',   // Purple
            'rgb(59, 130, 246)',   // Blue
            'rgb(236, 72, 153)',   // Pink
            'rgb(20, 184, 166)',   // Teal
            'rgb(245, 101, 101)',  // Light Red
            'rgb(168, 85, 247)',   // Violet
        ];
        
        foreach ($storeRevenues as $index => $storeRevenue) {
            $store = $stores->get($storeRevenue->store_id);
            if ($store && $storeRevenue->revenue > 0) {
                $labels[] = strlen($store->name) > 15 ? substr($store->name, 0, 15) . '...' : $store->name;
                $revenueData[] = round($storeRevenue->revenue, 2);
                $colors[] = $colorPalette[$index % count($colorPalette)];
            }
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Revenue (EGP)',
                    'data' => $revenueData,
                    'backgroundColor' => $colors,
                    'borderColor' => array_map(function($color) {
                        return str_replace('rgb', 'rgba', $color) . ', 1)';
                    }, $colors),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
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
                        'callback' => 'function(value) { return value.toLocaleString() + " EGP"; }',
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 45,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.parsed.y.toLocaleString() + " EGP"; }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}

