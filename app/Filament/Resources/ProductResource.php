<?php

namespace App\Filament\Resources;

use App\Core\Models\Product;
use App\Filament\Resources\ProductResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cube';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_management');
    }

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('navigation.products');
    }

    public static function getModelLabel(): string
    {
        return __('system.product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.products');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_products');
    }

    public static function canCreate(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_products');
    }

    public static function canEdit($record): bool
    {
        // Foodics items are read-only
        if ($record->external_source === 'foodics') {
            return false;
        }
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_products');
    }

    public static function canDelete($record): bool
    {
        // Foodics items cannot be deleted
        if ($record->external_source === 'foodics') {
            return false;
        }
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_products');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.product_information'))
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label(__('system.category'))
                            ->relationship('category', 'name_json')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getName(app()->getLocale()))
                            ->searchable(['name_json'])
                            ->required(),
                        Components\Tabs::make('name_json_tabs')
                            ->label(__('system.name'))
                            ->tabs([
                                Components\Tabs\Tab::make('en')
                                    ->label('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('name_json.en')
                                            ->label(__('system.name'))
                                            ->required()
                                            ->maxLength(255),
                                    ]),
                                Components\Tabs\Tab::make('ar')
                                    ->label('Arabic')
                                    ->schema([
                                        Forms\Components\TextInput::make('name_json.ar')
                                            ->label(__('system.name'))
                                            ->required()
                                            ->maxLength(255),
                                    ]),
                            ])
                            ->columnSpanFull(),
                        Components\Tabs::make('description_json_tabs')
                            ->label(__('system.description'))
                            ->tabs([
                                Components\Tabs\Tab::make('en')
                                    ->label('English')
                                    ->schema([
                                        Forms\Components\Textarea::make('description_json.en')
                                            ->label(__('system.description'))
                                            ->rows(3),
                                    ]),
                                Components\Tabs\Tab::make('ar')
                                    ->label('Arabic')
                                    ->schema([
                                        Forms\Components\Textarea::make('description_json.ar')
                                            ->label(__('system.description'))
                                            ->rows(3),
                                    ]),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('image')
                            ->label(__('system.image'))
                            ->image()
                            ->directory('products')
                            ->disk('public')
                            ->maxSize(2048)
                            ->imageEditor(),
                        Forms\Components\TextInput::make('base_price')
                            ->label(__('system.base_price'))
                            ->numeric()
                            ->prefix('SAR')
                            ->required()
                            ->step(0.01),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('system.is_active'))
                            ->default(true)
                            ->required(),
                    ]),
                Components\Section::make(__('system.foodics_integration'))
                    ->schema([
                        Forms\Components\Select::make('external_source')
                            ->label(__('system.external_source'))
                            ->options([
                                'local' => __('system.local'),
                                'foodics' => __('system.foodics'),
                            ])
                            ->default('local')
                            ->disabled(fn ($record) => $record && $record->external_source === 'foodics')
                            ->dehydrated(),
                        Forms\Components\TextInput::make('foodics_id')
                            ->label(__('system.foodics_id'))
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\DateTimePicker::make('last_synced_at')
                            ->label(__('system.last_synced_at'))
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label(__('system.image'))
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('system.name'))
                    ->formatStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('system.category'))
                    ->formatStateUsing(fn ($record) => $record->category?->getName(app()->getLocale()))
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label(__('system.base_price'))
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('system.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('external_source')
                    ->label(__('system.external_source'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'local' => 'success',
                        'foodics' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('foodics_id')
                    ->label(__('system.foodics_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('system.category'))
                    ->relationship('category', 'name_json')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getName(app()->getLocale())),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('system.is_active'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.active'))
                    ->falseLabel(__('system.inactive')),
                Tables\Filters\SelectFilter::make('external_source')
                    ->label(__('system.external_source'))
                    ->options([
                        'local' => __('system.local'),
                        'foodics' => __('system.foodics'),
                    ]),
                Tables\Filters\Filter::make('base_price')
                    ->form([
                        Forms\Components\TextInput::make('price_from')
                            ->label(__('system.price_from'))
                            ->numeric(),
                        Forms\Components\TextInput::make('price_to')
                            ->label(__('system.price_to'))
                            ->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['price_from'], fn ($q, $price) => $q->where('base_price', '>=', $price))
                            ->when($data['price_to'], fn ($q, $price) => $q->where('base_price', '<=', $price));
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn ($record) => $record->external_source !== 'foodics'),
                Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->external_source !== 'foodics'),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

