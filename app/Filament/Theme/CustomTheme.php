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
            $this->getRtlAsset(),
        ], 'custom-theme');
    }
    
    protected function getRtlAsset(): Asset
    {
        return Css::make('rtl-support', __DIR__ . '/../../resources/css/filament/admin/rtl.css');
    }

    public function boot(\Filament\Panel $panel): void
    {
        // Set HTML direction for RTL support
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_START,
            fn (): View => view('filament.components.html-direction'),
        );
        
        // Register topbar language switcher (before user menu)
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): View => view('filament.components.language-switcher-topbar'),
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
