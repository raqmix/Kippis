<?php

namespace App\Filament\Resources;

use App\Core\Models\Category;
use App\Filament\Resources\CategoryResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_management');
    }

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('navigation.categories');
    }

    public static function getModelLabel(): string
    {
        return __('system.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.categories');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public static function canCreate(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public static function canEdit($record): bool
    {
        // Foodics items are read-only
        if ($record->external_source === 'foodics') {
            return false;
        }
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public static function canDelete($record): bool
    {
        // Foodics items cannot be deleted
        if ($record->external_source === 'foodics') {
            return false;
        }
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.category_information'))
                    ->schema([
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
                            ->directory('categories')
                            ->disk('public')
                            ->maxSize(2048)
                            ->imageEditor(),
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
                Tables\Columns\TextColumn::make('name_json')
                    ->label(__('system.name'))
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable(query: fn ($query, string $search) => $query->where('name_json->en', 'like', "%{$search}%")->orWhere('name_json->ar', 'like', "%{$search}%"))
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("JSON_EXTRACT(name_json, '$.en') {$direction}")),
                Tables\Columns\TextColumn::make('description_json')
                    ->label(__('system.description'))
                    ->getStateUsing(fn ($record) => Str::limit($record->getDescription(app()->getLocale()), 50))
                    ->toggleable(),
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
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label(__('system.last_synced_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
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
                Tables\Filters\SelectFilter::make('external_source')
                    ->label(__('system.external_source'))
                    ->options([
                        'local' => __('system.local'),
                        'foodics' => __('system.foodics'),
                    ]),
                Tables\Filters\TernaryFilter::make('has_foodics_mapping')
                    ->label(__('system.has_foodics_mapping'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.yes'))
                    ->falseLabel(__('system.no'))
                    ->query(function ($query, array $data) {
                        if ($data['value'] === true) {
                            return $query->whereNotNull('foodics_id');
                        } elseif ($data['value'] === false) {
                            return $query->whereNull('foodics_id');
                        }
                        return $query;
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}

