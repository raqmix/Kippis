<?php

namespace App\Filament\Pages;

use App\Core\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * Admin knobs for the mobile-app store links surfaced on kippis-eg.com
 * and anywhere else the public "download the app" CTA appears.
 *
 * Every field round-trips Setting::get/set and reads back through the
 * public GET /v1/app-links endpoint, so the website badges reflect
 * changes instantly — no deploy needed to swap in a real App Store URL
 * when the TestFlight build goes public, or to flip the Android badge
 * from "Coming soon" to live when the Play Store listing lands.
 */
class AppLinksSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.app-links-settings';

    protected static ?int $navigationSort = 60;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-device-phone-mobile';
    }

    public static function getNavigationLabel(): string
    {
        return 'App download links';
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'App download links';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function canAccess(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_settings');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'app_ios_url' => (string) Setting::get('app.ios_url', ''),
            'app_ios_enabled' => (bool) Setting::get('app.ios_enabled', false),
            'app_android_url' => (string) Setting::get('app.android_url', ''),
            'app_android_enabled' => (bool) Setting::get('app.android_enabled', false),
            'app_section_headline_en' => (string) Setting::get('app.section_headline_en', 'Get the Kippis app'),
            'app_section_headline_ar' => (string) Setting::get('app.section_headline_ar', 'حمّل تطبيق كيبيس'),
            'app_section_body_en' => (string) Setting::get('app.section_body_en', 'Order ahead, track rewards, and unlock offers only in the app.'),
            'app_section_body_ar' => (string) Setting::get('app.section_body_ar', 'اطلب مسبقًا، تابع نقاطك، واحصل على عروض حصرية للتطبيق فقط.'),
        ]);
    }

    protected function form(Schema $schema): Schema
    {
        return $schema
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [
            Components\Section::make('iOS (App Store)')
                ->description('Enable once the App Store listing is live. The website hides the badge when this is off.')
                ->schema([
                    Forms\Components\Toggle::make('app_ios_enabled')
                        ->label('Show iOS badge'),
                    Forms\Components\TextInput::make('app_ios_url')
                        ->label('App Store URL')
                        ->url()
                        ->placeholder('https://apps.apple.com/app/kippis/id0000000000')
                        ->helperText('Full https://apps.apple.com/... URL. Only used when the toggle above is on.'),
                ])
                ->columns(2),

            Components\Section::make('Android (Google Play)')
                ->description('Enable once the Play Store listing is live. Off = "Coming soon" state or no badge (see badge component).')
                ->schema([
                    Forms\Components\Toggle::make('app_android_enabled')
                        ->label('Show Android badge'),
                    Forms\Components\TextInput::make('app_android_url')
                        ->label('Play Store URL')
                        ->url()
                        ->placeholder('https://play.google.com/store/apps/details?id=com.raqmix.kippis')
                        ->helperText('Full https://play.google.com/... URL. Only used when the toggle above is on.'),
                ])
                ->columns(2),

            Components\Section::make('Section copy')
                ->description('The headline + body shown next to the badges on the website. Bilingual — both languages are rendered based on the customer\'s locale.')
                ->schema([
                    Components\Tabs::make('copy_tabs')
                        ->tabs([
                            Components\Tabs\Tab::make('English')->schema([
                                Forms\Components\TextInput::make('app_section_headline_en')
                                    ->label('Headline (EN)')
                                    ->maxLength(80),
                                Forms\Components\Textarea::make('app_section_body_en')
                                    ->label('Body (EN)')
                                    ->rows(3)
                                    ->maxLength(240),
                            ]),
                            Components\Tabs\Tab::make('Arabic')->schema([
                                Forms\Components\TextInput::make('app_section_headline_ar')
                                    ->label('Headline (AR)')
                                    ->maxLength(80),
                                Forms\Components\Textarea::make('app_section_body_ar')
                                    ->label('Body (AR)')
                                    ->rows(3)
                                    ->maxLength(240),
                            ]),
                        ]),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save')
                ->icon('heroicon-o-check')
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $map = [
            'app_ios_url' => ['app.ios_url', 'string', 'app'],
            'app_ios_enabled' => ['app.ios_enabled', 'boolean', 'app'],
            'app_android_url' => ['app.android_url', 'string', 'app'],
            'app_android_enabled' => ['app.android_enabled', 'boolean', 'app'],
            'app_section_headline_en' => ['app.section_headline_en', 'string', 'app'],
            'app_section_headline_ar' => ['app.section_headline_ar', 'string', 'app'],
            'app_section_body_en' => ['app.section_body_en', 'string', 'app'],
            'app_section_body_ar' => ['app.section_body_ar', 'string', 'app'],
        ];

        foreach ($map as $formKey => [$settingKey, $type, $group]) {
            Setting::set($settingKey, $data[$formKey] ?? null, $type, $group);
        }

        Notification::make()
            ->title('App download links saved')
            ->success()
            ->send();
    }
}
