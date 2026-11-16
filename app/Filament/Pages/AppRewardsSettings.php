<?php

namespace App\Filament\Pages;

use App\Core\Models\Category;
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
 * App-only discount (app_offer.*) and Rewards-wallet presets (rewards.*).
 * Read by App\Services\AppOffer\AppOffer and App\Services\Rewards\RewardIssuer.
 */
class AppRewardsSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.app-rewards-settings';

    protected static ?int $navigationSort = 52;

    public ?array $data = [];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-device-phone-mobile';
    }

    public static function getNavigationLabel(): string
    {
        return __('rewards.admin.app_rewards_settings');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('rewards.admin.app_rewards_settings');
    }

    public static function canAccess(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_settings');
    }

    public function mount(): void
    {
        $this->form->fill([
            'app_offer_enabled'  => (bool) Setting::get('app_offer.enabled', true),
            'app_offer_percent'  => (int) Setting::get('app_offer.percent', 30),
            'app_offer_label_en' => (string) Setting::get('app_offer.label_en', ''),
            'app_offer_label_ar' => (string) Setting::get('app_offer.label_ar', ''),
            'free_coffee_category_ids'    => $this->ids(Setting::get('rewards.free_coffee.category_ids', [])),
            'free_coffee_max_value'       => (int) Setting::get('rewards.free_coffee.max_value', 130),
            'birthday_treat_enabled'      => (bool) Setting::get('birthday_treat.enabled', true),
            'birthday_drink_category_ids' => $this->ids(Setting::get('rewards.birthday_drink.category_ids', [])),
            'birthday_drink_max_value'    => (int) Setting::get('rewards.birthday_drink.max_value', 170),
            'wallet_ttl_days'             => (int) Setting::get('rewards.wallet_ttl_days', 30),
        ]);
    }

    protected function form(Schema $schema): Schema
    {
        return $schema->schema($this->getFormSchema())->statePath('data');
    }

    protected function getFormSchema(): array
    {
        $categories = fn () => Category::query()->orderBy('sort_order')->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->getName(app()->getLocale())])
            ->all();

        return [
            Components\Section::make(__('rewards.admin.app_offer'))
                ->icon('heroicon-o-device-phone-mobile')
                ->description(__('rewards.admin.app_offer_help'))
                ->schema([
                    Forms\Components\Toggle::make('app_offer_enabled')
                        ->label(__('rewards.admin.app_offer_enabled')),
                    Forms\Components\TextInput::make('app_offer_percent')
                        ->label(__('rewards.admin.app_offer_percent'))
                        ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                    Forms\Components\TextInput::make('app_offer_label_en')
                        ->label(__('rewards.admin.app_offer_label_en'))
                        ->helperText(__('rewards.admin.app_offer_label_help'))
                        ->maxLength(120),
                    Forms\Components\TextInput::make('app_offer_label_ar')
                        ->label(__('rewards.admin.app_offer_label_ar'))
                        ->helperText(__('rewards.admin.app_offer_label_help'))
                        ->maxLength(120),
                ])
                ->columns(2),

            Components\Section::make(__('rewards.admin.free_coffee'))
                ->icon('heroicon-o-gift')
                ->description(__('rewards.admin.free_coffee_help'))
                ->schema([
                    Forms\Components\Select::make('free_coffee_category_ids')
                        ->label(__('rewards.admin.categories'))
                        ->multiple()->options($categories),
                    Forms\Components\TextInput::make('free_coffee_max_value')
                        ->label(__('rewards.admin.max_value'))
                        ->numeric()->minValue(0)->required(),
                ])
                ->columns(2),

            Components\Section::make(__('rewards.admin.birthday_drink'))
                ->icon('heroicon-o-cake')
                ->schema([
                    Forms\Components\Toggle::make('birthday_treat_enabled')
                        ->label('Birthday treat enabled'),
                    Forms\Components\Select::make('birthday_drink_category_ids')
                        ->label(__('rewards.admin.categories'))
                        ->multiple()->options($categories),
                    Forms\Components\TextInput::make('birthday_drink_max_value')
                        ->label(__('rewards.admin.max_value'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('wallet_ttl_days')
                        ->label(__('rewards.admin.wallet_ttl_days'))
                        ->helperText(__('rewards.admin.wallet_ttl_days_help'))
                        ->numeric()->minValue(0)->required(),
                ])
                ->columns(2),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label(__('system.save'))
                ->icon('heroicon-o-check')
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $map = [
            'app_offer_enabled'  => ['app_offer.enabled',  'boolean', 'app_offer'],
            'app_offer_percent'  => ['app_offer.percent',  'number',  'app_offer'],
            'app_offer_label_en' => ['app_offer.label_en', 'string',  'app_offer'],
            'app_offer_label_ar' => ['app_offer.label_ar', 'string',  'app_offer'],
            'free_coffee_max_value'    => ['rewards.free_coffee.max_value',    'number', 'rewards'],
            'birthday_drink_max_value' => ['rewards.birthday_drink.max_value', 'number', 'rewards'],
            'birthday_treat_enabled'   => ['birthday_treat.enabled', 'boolean', 'rewards'],
            'wallet_ttl_days'          => ['rewards.wallet_ttl_days',          'number', 'rewards'],
        ];
        foreach ($map as $formKey => [$settingKey, $type, $group]) {
            Setting::set($settingKey, $data[$formKey] ?? null, $type, $group);
        }

        // Arrays are stored as json by Setting::set.
        Setting::set('rewards.free_coffee.category_ids', $this->ids($data['free_coffee_category_ids'] ?? []), 'json', 'rewards');
        Setting::set('rewards.birthday_drink.category_ids', $this->ids($data['birthday_drink_category_ids'] ?? []), 'json', 'rewards');

        Notification::make()
            ->title(__('rewards.admin.saved'))
            ->body(__('system.changes_have_been_applied'))
            ->success()
            ->send();
    }

    private function ids(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?? [];
        }

        return array_values(array_map('intval', (array) $value));
    }
}
