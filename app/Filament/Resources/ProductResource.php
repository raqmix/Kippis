<?php

namespace App\Filament\Resources;

use App\Core\Models\Category;
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
use Illuminate\Support\Str;

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
        // Foodics items used to be hard-blocked from editing. Admins now
        // need to override per-product fields (image, category, sort, etc.)
        // — the catalog sync respects those edits via
        // products.locally_overridden_fields, so the catalog re-sync no
        // longer wipes them on the next run.
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_products');
    }

    public static function canDelete($record): bool
    {
        // Deleting a Foodics-synced product would just be re-created on
        // the next sync, so we still block it. Use is_active to hide
        // instead — that field is editable and tracked as a local override.
        if ($record->external_source === 'foodics') {
            return false;
        }
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_products');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Friendly heads-up on Foodics-synced products so admins
                // know edits stick across catalog re-syncs.
                Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('foodics_edit_notice')
                            ->hiddenLabel()
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div style="color:#92400e">'
                                . '<strong>Foodics-synced product.</strong> '
                                . 'Any field you change here will be preserved when the '
                                . 'catalog re-syncs from Foodics. The original Foodics '
                                . 'value will only come back if you clear the override.'
                                . '</div>'
                            )),
                    ])
                    ->visible(fn ($record) => $record && $record->external_source === 'foodics')
                    ->compact(),
                Components\Section::make(__('system.product_information'))
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label(__('system.category'))
                            ->relationship('category', 'name_json')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getName(app()->getLocale()))
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                return Category::query()
                                    ->whereRaw('LOWER(name_json) LIKE ?', ['%' . strtolower($search) . '%'])
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($category) => [$category->id => $category->getName(app()->getLocale())])
                                    ->toArray();
                            })
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
                            ->visibility('public')
                            ->maxSize(2048)
                            ->imageEditor(),
                        Forms\Components\Select::make('product_kind')
                            ->label(__('system.product_kind'))
                            ->options([
                                'regular' => __('system.regular'),
                                'mix_base' => __('system.mix_base'),
                            ])
                            ->default('regular')
                            ->required()
                            ->helperText(__('system.product_kind_helper')),
                        Forms\Components\TextInput::make('base_price')
                            ->label(__('system.base_price'))
                            ->numeric()
                            ->prefix('EGP')
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
                        Forms\Components\Placeholder::make('locally_overridden_fields_view')
                            ->label('Locally overridden fields')
                            ->columnSpanFull()
                            ->content(function ($record) {
                                $list = $record?->locally_overridden_fields ?? [];
                                if (!is_array($list) || empty($list)) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<span style="color:#6b7280">None — the sync controls every field.</span>'
                                    );
                                }
                                return new \Illuminate\Support\HtmlString(
                                    '<code>' . htmlspecialchars(implode(', ', $list)) . '</code>'
                                );
                            })
                            ->visible(fn ($record) => $record && $record->external_source === 'foodics'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['category']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label(__('system.image'))
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn ($record) => str_starts_with((string)$record->image, 'http') ? $record->image : null)
                    ->getStateUsing(fn ($record) => str_starts_with((string)$record->image, 'http') ? null : $record->image),
                Tables\Columns\TextColumn::make('name_json')
                    ->label(__('system.name'))
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable(query: fn ($query, string $search) => $query->where('name_json->en', 'like', "%{$search}%")->orWhere('name_json->ar', 'like', "%{$search}%"))
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("JSON_EXTRACT(name_json, '$.en') {$direction}")),
                Tables\Columns\TextColumn::make('description_json')
                    ->label(__('system.description'))
                    ->getStateUsing(fn ($record) => Str::limit($record->getDescription(app()->getLocale()), 50))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.name_json')
                    ->label(__('system.category'))
                    ->getStateUsing(fn ($record) => $record->category?->getName(app()->getLocale()))
                    ->searchable(query: fn ($query, string $search) => $query->whereHas('category', fn ($q) => $q->where('name_json->en', 'like', "%{$search}%")->orWhere('name_json->ar', 'like', "%{$search}%")))
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_kind')
                    ->label(__('system.product_kind'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'regular' => 'success',
                        'mix_base' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'regular' => __('system.regular'),
                        'mix_base' => __('system.mix_base'),
                        default => $state,
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label(__('system.base_price'))
                    ->money('EGP')
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
                    ->options(fn () => Category::query()
                        ->get()
                        ->mapWithKeys(fn ($category) => [
                            $category->id => $category->getName(app()->getLocale()),
                        ])
                        ->all())
                    ->searchable(),
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
                Tables\Filters\SelectFilter::make('product_kind')
                    ->label(__('system.product_kind'))
                    ->options([
                        'regular' => __('system.regular'),
                        'mix_base' => __('system.mix_base'),
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
                Actions\EditAction::make(),
                Actions\Action::make('clear_foodics_overrides')
                    ->label('Clear overrides')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Drops all local edits on this Foodics product. The next catalog sync will replace those fields with Foodics\' values.')
                    ->visible(fn ($record) => $record->external_source === 'foodics'
                        && is_array($record->locally_overridden_fields)
                        && !empty($record->locally_overridden_fields))
                    ->action(function ($record) {
                        $record->update(['locally_overridden_fields' => []]);
                    }),
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
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            ProductResource\RelationManagers\ProductAddonsRelationManager::class,
            ProductResource\RelationManagers\MixBuilderBasesRelationManager::class,
            ProductResource\RelationManagers\FoodicsModifiersRelationManager::class,
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

