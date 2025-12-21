<?php

namespace App\Filament\Pages;

use App\Core\Services\LocalizationService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Settings extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cog-6-tooth';
    }
    
    protected string $view = 'filament.pages.settings';
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.settings');
    }
    
    protected static ?int $navigationSort = 999;
    
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    
    public ?string $locale = null;
    
    public function mount(): void
    {
        $admin = Auth::guard('admin')->user();
        $this->locale = $admin->locale ?? 'en';
    }
    
    public function getAvailableLocales(): array
    {
        $localizationService = app(LocalizationService::class);
        return $localizationService->getAvailableLocales();
    }
    
    public function updateLocale(): void
    {
        $admin = Auth::guard('admin')->user();
        
        if ($admin && $this->locale) {
            $admin->update(['locale' => $this->locale]);
            app()->setLocale($this->locale);
            session(['locale' => $this->locale]);
            
            notify()->success(
                __('system.language_updated_successfully'),
                __('system.language_changes_applied')
            );
            
            // Reload the page to apply the new locale
            $this->redirect(request()->header('Referer') ?? route('filament.admin.pages.settings'));
        }
    }
}

