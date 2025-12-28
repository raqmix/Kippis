<?php

namespace App\Filament\Resources;

use App\Core\Models\PromoCode;
use App\Filament\Resources\PromoCodeResource\Pages;
use App\Filament\Resources\PromoCodeResource\RelationManagers\UsagesRelationManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-ticket';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_management');
    }

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('navigation.promo_codes');
    }

    public static function getModelLabel(): string
    {
        return __('system.promo_code');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.promo_codes');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function canCreate(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function canEdit($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.promo_code_information'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('system.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->uppercase(),
                        Forms\Components\Select::make('discount_type')
                            ->label(__('system.discount_type'))
                            ->options([
                                'percentage' => __('system.percentage'),
                                'fixed' => __('system.fixed'),
                            ])
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('discount_value')
                            ->label(__('system.discount_value'))
                            ->numeric()
                            ->required()
                            ->suffix(fn ($get) => $get('discount_type') === 'percentage' ? '%' : 'SAR')
                            ->step(0.01),
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label(__('system.valid_from'))
                            ->required(),
                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label(__('system.valid_to'))
                            ->required()
                            ->after('valid_from'),
                        Forms\Components\TextInput::make('usage_limit')
                            ->label(__('system.usage_limit'))
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('usage_per_user_limit')
                            ->label(__('system.usage_per_user_limit'))
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('minimum_order_amount')
                            ->label(__('system.minimum_order_amount'))
                            ->numeric()
                            ->prefix('SAR')
                            ->default(0)
                            ->step(0.01),
                        Forms\Components\Toggle::make('active')
                            ->label(__('system.active'))
                            ->default(true)
                            ->required(),
                    ]),
                Components\Section::make(__('system.scoping'))
                    ->description(__('system.scoping_description'))
                    ->schema([
                        Forms\Components\CheckboxList::make('stores')
                            ->label(__('system.stores'))
                            ->relationship('stores', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),
                        Forms\Components\CheckboxList::make('categories')
                            ->label(__('system.categories'))
                            ->relationship('categories', 'name_json')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getName(app()->getLocale())),
                        Forms\Components\CheckboxList::make('products')
                            ->label(__('system.products'))
                            ->relationship('products', 'name_json')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getName(app()->getLocale())),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('system.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('discount_type')
                    ->label(__('system.discount_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->discount_value . ($state === 'percentage' ? '%' : ' SAR'))
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'info',
                        'fixed' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label(__('system.valid_from'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_to')
                    ->label(__('system.valid_to'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('used_count')
                    ->label(__('system.used_count'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_limit')
                    ->label(__('system.usage_limit'))
                    ->formatStateUsing(fn ($state, $record) => $state ? "{$record->used_count}/{$state}" : $record->used_count)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('active')
                    ->label(__('system.active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label(__('system.active'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.active'))
                    ->falseLabel(__('system.inactive')),
                Tables\Filters\SelectFilter::make('discount_type')
                    ->label(__('system.discount_type'))
                    ->options([
                        'percentage' => __('system.percentage'),
                        'fixed' => __('system.fixed'),
                    ]),
                Tables\Filters\Filter::make('valid_date')
                    ->form([
                        Forms\Components\DatePicker::make('valid_from')
                            ->label(__('system.valid_from')),
                        Forms\Components\DatePicker::make('valid_to')
                            ->label(__('system.valid_to')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['valid_from'], fn ($q, $date) => $q->where('valid_from', '>=', $date))
                            ->when($data['valid_to'], fn ($q, $date) => $q->where('valid_to', '<=', $date));
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),
                Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            UsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'view' => Pages\ViewPromoCode::route('/{record}'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}

