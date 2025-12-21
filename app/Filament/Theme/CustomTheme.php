<?php

namespace App\Filament\Theme;

use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class CustomTheme implements \Filament\Contracts\Plugin
{
    public function getId(): string
    {
        return 'custom-theme';
    }

    public function register(\Filament\Panel $panel): void
    {
        FilamentAsset::register([
            $this->getThemeAsset(),
        ], 'custom-theme');
    }

    public function boot(\Filament\Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_END,
            fn (): View => view('filament.components.language-switcher-sidebar'),
        );
    }

    protected function getThemeAsset(): Asset
    {
        return Css::make('custom-theme', __DIR__ . '/../../resources/css/filament/admin/theme.css');
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
