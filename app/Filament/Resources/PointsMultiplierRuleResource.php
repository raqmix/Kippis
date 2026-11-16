<?php

namespace App\Filament\Resources;

use App\Core\Models\PointsMultiplierRule;
use App\Filament\Resources\PointsMultiplierRuleResource\Pages;
use App\Support\ClientPlatform;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Double-points rules (Happy Hours, Monday Refill). App orders only; the
 * highest matching multiplier wins. See PointsMultiplierRule.
 */
class PointsMultiplierRuleResource extends Resource
{
    protected static ?string $model = PointsMultiplierRule::class;

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty');
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return self::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty_rules.rules.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty_rules.rules.model');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bolt';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Components\Section::make(__('loyalty_rules.rules.model'))
                ->description(__('loyalty_rules.rules.help'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('loyalty_rules.rules.name'))
                        ->required()->maxLength(120),
                    Forms\Components\TextInput::make('multiplier')
                        ->label(__('loyalty_rules.rules.multiplier'))
                        ->numeric()->default(2)->minValue(1)->maxValue(20)->step(0.25)->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('loyalty_rules.rules.is_active'))
                        ->default(true),
                ])
                ->columns(3),

            Components\Section::make(__('loyalty_rules.rules.when'))
                ->schema([
                    Forms\Components\CheckboxList::make('days_of_week')
                        ->label(__('loyalty_rules.rules.days'))
                        ->helperText(__('loyalty_rules.rules.days_help'))
                        ->options([
                            1 => __('loyalty_rules.days.1'), 2 => __('loyalty_rules.days.2'),
                            3 => __('loyalty_rules.days.3'), 4 => __('loyalty_rules.days.4'),
                            5 => __('loyalty_rules.days.5'), 6 => __('loyalty_rules.days.6'),
                            7 => __('loyalty_rules.days.7'),
                        ])
                        ->columns(4),
                    Forms\Components\TimePicker::make('time_from')
                        ->label(__('loyalty_rules.rules.time_from'))
                        ->seconds(false)
                        ->helperText(__('loyalty_rules.rules.time_help')),
                    Forms\Components\TimePicker::make('time_to')
                        ->label(__('loyalty_rules.rules.time_to'))
                        ->seconds(false),
                    Forms\Components\DatePicker::make('starts_at')
                        ->label(__('loyalty_rules.rules.starts_at'))
                        ->helperText(__('loyalty_rules.rules.dates_help')),
                    Forms\Components\DatePicker::make('ends_at')
                        ->label(__('loyalty_rules.rules.ends_at')),
                    Forms\Components\Select::make('platforms')
                        ->label(__('loyalty_rules.rules.platforms'))
                        ->helperText(__('loyalty_rules.rules.platforms_help'))
                        ->multiple()
                        ->options([
                            ClientPlatform::MOBILE => 'mobile',
                            ClientPlatform::WEB => 'web',
                        ]),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('multiplier')->formatStateUsing(fn ($state) => 'x' . (float) $state),
                Tables\Columns\TextColumn::make('days_of_week')
                    ->label(__('loyalty_rules.rules.days'))
                    ->formatStateUsing(fn ($state) => $state
                        ? implode(', ', array_map(fn ($d) => __('loyalty_rules.days.' . $d), (array) $state))
                        : __('loyalty_rules.rules.every_day')),
                Tables\Columns\TextColumn::make('time_from')
                    ->label(__('loyalty_rules.rules.time'))
                    ->formatStateUsing(fn ($state, PointsMultiplierRule $r) => ($r->time_from || $r->time_to)
                        ? substr((string) ($r->time_from ?? '00:00'), 0, 5) . ' - ' . substr((string) ($r->time_to ?? '24:00'), 0, 5)
                        : __('loyalty_rules.rules.all_day')),
                Tables\Columns\TextColumn::make('starts_at')->date()->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')->date()->placeholder('-')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([Tables\Filters\TernaryFilter::make('is_active')])
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPointsMultiplierRules::route('/'),
            'create' => Pages\CreatePointsMultiplierRule::route('/create'),
            'edit'   => Pages\EditPointsMultiplierRule::route('/{record}/edit'),
        ];
    }
}
