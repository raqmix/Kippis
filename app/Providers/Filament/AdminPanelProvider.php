<?php

namespace App\Providers\Filament;

use App\Core\Models\Admin;
use App\Filament\Theme\CustomTheme;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::hex('#7B6CF6'),
                'success' => Color::hex('#22C55E'),
                'warning' => Color::hex('#F59E0B'),
                'danger' => Color::hex('#EF4444'),
                'info' => Color::hex('#4FD1C5'),
                'gray' => Color::Zinc,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SetLocale::class,
                \App\Http\Middleware\TrackLoginAttempts::class,
                \App\Http\Middleware\LogAdminActivity::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('admin')
            ->authPasswordBroker('admins')
            ->brandName('Kippis')
            ->favicon(asset('favicon.ico'))
            ->userMenuItems([
                // Notifications removed
            ])
            ->navigationGroups([
                __('navigation.groups.system_management'),
                __('navigation.groups.security'),
                __('navigation.groups.content_management'),
                __('navigation.groups.support'),
                __('navigation.groups.integrations'),
                __('navigation.groups.monitoring'),
            ])
            ->plugins([
                CustomTheme::make(),
            ])
            ->discoverLivewireComponents(in: app_path('Filament'), for: 'App\\Filament')
            ->discoverLivewireComponents(in: app_path('Livewire'), for: 'App\\Livewire');
    }
}
