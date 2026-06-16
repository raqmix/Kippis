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
 * Admin-side knobs for the loyalty system. Everything customers
 * earn / redeem flows through these — points-per-EGP, welcome bonus,
 * check-in streaks, referral rewards, feedback points.
 *
 * Each form field round-trips Setting::get/set with the matching key,
 * so service-layer code (CustomerAuthService, OrderObserver,
 * CheckInService, ReferralService, OrderRatingService, RefundService)
 * picks up the change instantly — no deploy required for tuning.
 */
class LoyaltySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.loyalty-settings';

    protected static ?int $navigationSort = 50;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-gift';
    }

    public static function getNavigationLabel(): string
    {
        return __('system.loyalty_settings');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('system.loyalty_settings');
    }

    public static function canAccess(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_settings');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Loyalty basics
            'loyalty_welcome_bonus_points' => (int) Setting::get(
                'loyalty.welcome_bonus_points',
                (int) config('core.loyalty.welcome_bonus_points', 100),
            ),
            'loyalty_points_per_order_egp' => (int) Setting::get(
                'loyalty.points_per_order_egp',
                (int) config('core.loyalty.points_per_order_egp', 1),
            ),
            // Check-in
            'check_in_enabled'         => (bool) Setting::get('check_in.enabled', true),
            'check_in_daily_points'    => (int) Setting::get('check_in.daily_points', 3),
            'check_in_streak_3_bonus'  => (int) Setting::get('check_in.streak_3_bonus', 5),
            'check_in_streak_7_reward_type' => (string) Setting::get('check_in.streak_7_reward_type', 'free_addon'),
            'check_in_streak_7_bonus'  => (int) Setting::get('check_in.streak_7_bonus', 20),
            'check_in_streak_14_bonus' => (int) Setting::get('check_in.streak_14_bonus', 15),
            // Referral
            'referral_enabled'         => (bool) Setting::get('referral.enabled', true),
            'referral_code_prefix'     => (string) Setting::get('referral.code_prefix', 'KIPPIS'),
            'referral_monthly_cap'     => (int) Setting::get('referral.monthly_cap', 5),
            'referral_inviter_points'  => (int) Setting::get('referral.inviter_points', 30),
            'referral_invitee_points'  => (int) Setting::get('referral.invitee_points', 30),
            // Feedback
            'feedback_enabled'         => (bool) Setting::get('feedback.enabled', true),
            'feedback_points_per_rating' => (int) Setting::get('feedback.points_per_rating', 5),
            // Redemption
            'loyalty_redemption_enabled'    => (bool) Setting::get('loyalty.redemption_enabled', true),
            'loyalty_points_to_egp_rate'    => (int) Setting::get('loyalty.points_to_egp_rate', 10),
            'loyalty_max_points_per_order'  => (int) Setting::get('loyalty.max_points_per_order', 0),
            'loyalty_wallet_item_ttl_days'  => (int) Setting::get('loyalty.wallet_item_ttl_days', 30),
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
            Components\Section::make(__('system.loyalty_basics'))
                ->icon('heroicon-o-star')
                ->description(__('system.loyalty_basics_help'))
                ->schema([
                    Forms\Components\TextInput::make('loyalty_welcome_bonus_points')
                        ->label(__('system.welcome_bonus_points'))
                        ->helperText(__('system.welcome_bonus_points_help'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('loyalty_points_per_order_egp')
                        ->label(__('system.points_per_egp'))
                        ->helperText(__('system.points_per_egp_help'))
                        ->numeric()->minValue(0)->required(),
                ])
                ->columns(2),

            Components\Section::make(__('system.daily_check_in'))
                ->icon('heroicon-o-calendar-days')
                ->description(__('system.daily_check_in_help'))
                ->schema([
                    Forms\Components\Toggle::make('check_in_enabled')
                        ->label(__('system.check_in_enabled')),
                    Forms\Components\TextInput::make('check_in_daily_points')
                        ->label(__('system.check_in_daily_points'))
                        ->helperText(__('system.check_in_daily_points_help'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('check_in_streak_3_bonus')
                        ->label(__('system.streak_3_bonus'))
                        ->helperText(__('system.streak_3_bonus_help'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\Select::make('check_in_streak_7_reward_type')
                        ->label(__('system.streak_7_reward_type'))
                        ->helperText(__('system.streak_7_reward_type_help'))
                        ->options([
                            'free_addon' => __('system.streak_reward_free_addon'),
                            'points'     => __('system.streak_reward_points'),
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('check_in_streak_7_bonus')
                        ->label(__('system.streak_7_bonus'))
                        ->helperText(__('system.streak_7_bonus_help'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('check_in_streak_14_bonus')
                        ->label(__('system.streak_14_bonus'))
                        ->helperText(__('system.streak_14_bonus_help'))
                        ->numeric()->minValue(0)->required(),
                ])
                ->columns(2),

            Components\Section::make(__('system.referral_program'))
                ->icon('heroicon-o-user-plus')
                ->description(__('system.referral_program_help'))
                ->schema([
                    Forms\Components\Toggle::make('referral_enabled')
                        ->label(__('system.referral_enabled')),
                    Forms\Components\TextInput::make('referral_code_prefix')
                        ->label(__('system.referral_code_prefix'))
                        ->helperText(__('system.referral_code_prefix_help'))
                        ->maxLength(8)->required(),
                    Forms\Components\TextInput::make('referral_monthly_cap')
                        ->label(__('system.referral_monthly_cap'))
                        ->helperText(__('system.referral_monthly_cap_help'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('referral_inviter_points')
                        ->label(__('system.referral_inviter_points'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('referral_invitee_points')
                        ->label(__('system.referral_invitee_points'))
                        ->numeric()->minValue(0)->required(),
                ])
                ->columns(2),

            Components\Section::make(__('system.order_feedback'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->description(__('system.order_feedback_help'))
                ->schema([
                    Forms\Components\Toggle::make('feedback_enabled')
                        ->label(__('system.feedback_enabled')),
                    Forms\Components\TextInput::make('feedback_points_per_rating')
                        ->label(__('system.feedback_points_per_rating'))
                        ->helperText(__('system.feedback_points_per_rating_help'))
                        ->numeric()->minValue(0)->required(),
                ])
                ->columns(2),

            Components\Section::make(__('system.points_redemption'))
                ->icon('heroicon-o-gift')
                ->description(__('system.points_redemption_help'))
                ->schema([
                    Forms\Components\Toggle::make('loyalty_redemption_enabled')
                        ->label(__('system.redemption_enabled')),
                    Forms\Components\TextInput::make('loyalty_points_to_egp_rate')
                        ->label(__('system.points_to_egp_rate'))
                        ->helperText(__('system.points_to_egp_rate_help'))
                        ->numeric()->minValue(1)->required(),
                    Forms\Components\TextInput::make('loyalty_max_points_per_order')
                        ->label(__('system.max_points_per_order'))
                        ->helperText(__('system.max_points_per_order_help'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\TextInput::make('loyalty_wallet_item_ttl_days')
                        ->label(__('system.wallet_item_ttl_days'))
                        ->helperText(__('system.wallet_item_ttl_days_help'))
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

        // Map form field → (setting key, type, group). Group lets the
        // /v1/settings/group/{group} API return only the keys a screen
        // needs, matching the existing contact/social fan-out pattern.
        $map = [
            'loyalty_welcome_bonus_points' => ['loyalty.welcome_bonus_points', 'number', 'loyalty'],
            'loyalty_points_per_order_egp' => ['loyalty.points_per_order_egp', 'number', 'loyalty'],
            'check_in_enabled'         => ['check_in.enabled',         'boolean', 'check_in'],
            'check_in_daily_points'    => ['check_in.daily_points',    'number',  'check_in'],
            'check_in_streak_3_bonus'  => ['check_in.streak_3_bonus',  'number',  'check_in'],
            'check_in_streak_7_reward_type' => ['check_in.streak_7_reward_type', 'string', 'check_in'],
            'check_in_streak_7_bonus'  => ['check_in.streak_7_bonus',  'number',  'check_in'],
            'check_in_streak_14_bonus' => ['check_in.streak_14_bonus', 'number',  'check_in'],
            'referral_enabled'         => ['referral.enabled',         'boolean', 'referral'],
            'referral_code_prefix'     => ['referral.code_prefix',     'string',  'referral'],
            'referral_monthly_cap'     => ['referral.monthly_cap',     'number',  'referral'],
            'referral_inviter_points'  => ['referral.inviter_points',  'number',  'referral'],
            'referral_invitee_points'  => ['referral.invitee_points',  'number',  'referral'],
            'feedback_enabled'         => ['feedback.enabled',         'boolean', 'feedback'],
            'feedback_points_per_rating' => ['feedback.points_per_rating', 'number', 'feedback'],
            'loyalty_redemption_enabled'    => ['loyalty.redemption_enabled',    'boolean', 'loyalty'],
            'loyalty_points_to_egp_rate'    => ['loyalty.points_to_egp_rate',    'number',  'loyalty'],
            'loyalty_max_points_per_order'  => ['loyalty.max_points_per_order',  'number',  'loyalty'],
            'loyalty_wallet_item_ttl_days'  => ['loyalty.wallet_item_ttl_days',  'number',  'loyalty'],
        ];

        foreach ($map as $formKey => [$settingKey, $type, $group]) {
            $value = $data[$formKey] ?? null;
            // Setting::set auto-detects booleans + arrays; passing
            // 'number' explicitly keeps the column cast right for ints.
            Setting::set($settingKey, $value, $type, $group);
        }

        Notification::make()
            ->title(__('system.loyalty_settings_saved'))
            ->body(__('system.changes_have_been_applied'))
            ->success()
            ->send();
    }
}
