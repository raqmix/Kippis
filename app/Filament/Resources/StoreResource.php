<?php

namespace App\Filament\Resources;

use App\Core\Models\Store;
use App\Filament\Resources\StoreResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-storefront';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.integrations');
    }

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('navigation.stores');
    }

    public static function getModelLabel(): string
    {
        return __('system.store');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.stores');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_stores');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.store_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('system.name'))
                            ->required()
                            ->maxLength(255),
                        Components\Tabs::make('name_localized_tabs')
                            ->label(__('system.name_localized'))
                            ->tabs([
                                Components\Tabs\Tab::make('en')
                                    ->label('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('name_localized.en')
                                            ->label(__('system.name'))
                                            ->maxLength(255),
                                    ]),
                                Components\Tabs\Tab::make('ar')
                                    ->label('Arabic')
                                    ->schema([
                                        Forms\Components\TextInput::make('name_localized.ar')
                                            ->label(__('system.name'))
                                            ->maxLength(255),
                                    ]),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('address')
                            ->label(__('system.address'))
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\TextInput::make('latitude')
                            ->label(__('system.latitude'))
                            ->numeric()
                            ->step(0.00000001)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('longitude')
                            ->label(__('system.longitude'))
                            ->numeric()
                            ->step(0.00000001)
                            ->maxLength(255),
                    ])->columns(2),
                Components\Section::make(__('system.operating_hours'))
                    ->schema([
                        Forms\Components\TimePicker::make('open_time')
                            ->label(__('system.open_time'))
                            ->seconds(false),
                        Forms\Components\TimePicker::make('close_time')
                            ->label(__('system.close_time'))
                            ->seconds(false),
                    ])->columns(2),
                Components\Section::make(__('system.status_settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('system.is_active'))
                            ->default(true)
                            ->required(),
                        Forms\Components\Toggle::make('receive_online_orders')
                            ->label(__('system.receive_online_orders'))
                            ->default(true)
                            ->required(),
                    ])->columns(2),
                Components\Section::make(__('system.foodics_integration'))
                    ->schema([
                        Forms\Components\TextInput::make('foodics_branch_id')
                            ->label(__('system.foodics_branch_id'))
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\DateTimePicker::make('synced_from_foodics_at')
                            ->label(__('system.synced_from_foodics_at'))
                            ->disabled()
                            ->dehydrated(),
                    ])->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('system.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->label(__('system.address'))
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('latitude')
                    ->label(__('system.latitude'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('longitude')
                    ->label(__('system.longitude'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('open_time')
                    ->label(__('system.open_time'))
                    ->time()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('close_time')
                    ->label(__('system.close_time'))
                    ->time()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('system.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('receive_online_orders')
                    ->label(__('system.receive_online_orders'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('foodics_branch_id')
                    ->label(__('system.foodics_branch_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('synced_from_foodics_at')
                    ->label(__('system.synced_from_foodics_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label(__('system.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('system.is_active'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.active'))
                    ->falseLabel(__('system.inactive')),
                Tables\Filters\TernaryFilter::make('receive_online_orders')
                    ->label(__('system.receive_online_orders'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.yes'))
                    ->falseLabel(__('system.no')),
                Tables\Filters\TernaryFilter::make('has_foodics_mapping')
                    ->label(__('system.has_foodics_mapping'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.yes'))
                    ->falseLabel(__('system.no'))
                    ->query(function ($query, array $data) {
                        if ($data['value'] === true) {
                            return $query->hasFoodicsMapping();
                        } elseif ($data['value'] === false) {
                            return $query->whereNull('foodics_branch_id');
                        }
                        return $query;
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'view' => Pages\ViewStore::route('/{record}'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}

