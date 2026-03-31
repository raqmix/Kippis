<?php

namespace App\Filament\Resources;

use App\Core\Models\FoodicsModifier;
use App\Filament\Resources\ModifierResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ModifierResource extends Resource
{
    protected static ?string $model = FoodicsModifier::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_management');
    }

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('navigation.modifiers');
    }

    public static function getModelLabel(): string
    {
        return __('system.modifier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.modifiers');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_modifiers');
    }

    public static function canCreate(): bool
    {
        return false; // Managed via Foodics sync only
    }

    public static function canEdit($record): bool
    {
        return false; // Managed via Foodics sync only
    }

    public static function canDelete($record): bool
    {
        return false; // Managed via Foodics sync only
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.modifier_information'))
                    ->schema([
                        Forms\Components\TextInput::make('foodics_id')
                            ->label('Foodics ID')
                            ->disabled()
                            ->columnSpanFull(),
                        Components\Tabs::make('name_json_tabs')
                            ->label(__('system.name'))
                            ->tabs([
                                Components\Tabs\Tab::make('en')
                                    ->label('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('name_json.en')
                                            ->label(__('system.name'))
                                            ->disabled(),
                                    ]),
                                Components\Tabs\Tab::make('ar')
                                    ->label('Arabic')
                                    ->schema([
                                        Forms\Components\TextInput::make('name_json.ar')
                                            ->label(__('system.name'))
                                            ->disabled(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('last_synced_at')
                            ->label(__('system.last_synced_at'))
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('system.name'))
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable(query: fn ($query, string $search) =>
                        $query->where('name_json->en', 'like', "%{$search}%")
                              ->orWhere('name_json->ar', 'like', "%{$search}%")
                    )
                    ->sortable(query: fn ($query, string $direction) =>
                        $query->orderBy('name_json->' . app()->getLocale(), $direction)
                    ),
                Tables\Columns\TextColumn::make('options_count')
                    ->label('Options')
                    ->counts('options')
                    ->sortable(),
                Tables\Columns\TextColumn::make('foodics_id')
                    ->label('Foodics ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label(__('system.last_synced_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name_json->en', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            ModifierResource\RelationManagers\OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModifiers::route('/'),
            'view' => Pages\ViewModifier::route('/{record}'),
        ];
    }
}

