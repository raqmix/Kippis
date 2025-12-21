<?php

namespace App\Filament\Widgets;

use App\Core\Models\ActivityLog;
use Filament\Widgets\ChartWidget;

class ActivityChartWidget extends ChartWidget
{
    public function getHeading(): ?string
    {
        return 'Activity Logs (Last 30 Days)';
    }
    
    protected function getData(): array
    {
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $data['labels'][] = $date->format('M d');
            $data['count'][] = ActivityLog::whereDate('created_at', $date)->count();
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Activities',
                    'data' => $data['count'] ?? [],
                ],
            ],
            'labels' => $data['labels'] ?? [],
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}

