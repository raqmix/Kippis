<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SecurityStatsWidget;
use App\Filament\Widgets\FailedLoginAttemptsWidget;
use Filament\Pages\Page;

class SecurityDashboard extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }
    
    protected string $view = 'filament.pages.security-dashboard';
    protected static ?int $navigationSort = 0;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.security_dashboard');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.security');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SecurityStatsWidget::class,
            FailedLoginAttemptsWidget::class,
        ];
    }
}

