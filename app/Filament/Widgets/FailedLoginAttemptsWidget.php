<?php

namespace App\Filament\Widgets;

use App\Core\Models\LoginAttempt;
use Filament\Widgets\ChartWidget;

class FailedLoginAttemptsWidget extends ChartWidget
{
    public static function canView(): bool
    {
        // Prevent auto-discovery on main Dashboard
        // Widgets explicitly added via getHeaderWidgets()/getWidgets() will still display
        return false;
    }

    public function getHeading(): ?string
    {
        return 'Failed Login Attempts (Last 7 Days)';
    }
    
    protected function getData(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $data['labels'][] = $date->format('M d');
            $data['failed'][] = LoginAttempt::where('success', false)
                ->whereDate('attempted_at', $date)
                ->count();
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Failed Attempts',
                    'data' => $data['failed'] ?? [],
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

