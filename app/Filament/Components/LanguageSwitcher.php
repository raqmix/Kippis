<?php

namespace App\Filament\Components;

use App\Core\Services\LocalizationService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\ActionSize;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LanguageSwitcher extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

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

    public function switchLanguageAction(): Action
    {
        $localizationService = app(LocalizationService::class);
        $locales = $localizationService->getAvailableLocales();
        
        return Action::make('switchLanguage')
            ->label(__('system.language'))
            ->icon('heroicon-o-language')
            ->iconButton()
            ->size(ActionSize::Small)
            ->dropdown()
            ->items(array_map(function ($code, $name) {
                return Action::make($code)
                    ->label($name)
                    ->icon($this->locale === $code ? 'heroicon-o-check' : null)
                    ->action(function () use ($code) {
                        $this->updateLocale($code);
                    });
            }, array_keys($locales), $locales));
    }

    public function updateLocale(string $locale): void
    {
        $admin = Auth::guard('admin')->user();
        
        if ($admin) {
            $admin->update(['locale' => $locale]);
            app()->setLocale($locale);
            session(['locale' => $locale]);
            
            $this->locale = $locale;
            
            $this->dispatch('locale-changed', locale: $locale);
            
            $this->redirect(request()->header('Referer') ?? url()->current());
        }
    }

    public function render(): View
    {
        return view('filament.components.language-switcher');
    }
}

