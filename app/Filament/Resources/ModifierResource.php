<?php

namespace App\Filament\Resources;

use App\Core\Models\Modifier;
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
    protected static ?string $model = Modifier::class;

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
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_modifiers');
    }

    public static function canEdit($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_modifiers');
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_modifiers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.modifier_information'))
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label(__('system.type'))
                            ->options([
                                'size' => __('system.size'),
                                'smothing' => __('system.smothing'),
                                'customize_modifires' => __('system.customize_modifires'),
                                'extra' => __('system.extra'),
                            ])
                            ->required()
                            ->rules(['required', 'in:size,smothing,customize_modifires,extra'])
                            ->reactive(),
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
                        Forms\Components\TextInput::make('max_level')
                            ->label(__('system.max_level'))
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('price')
                            ->label(__('system.price'))
                            ->numeric()
                            ->prefix('SAR')
                            ->required()
                            ->default(0)
                            ->step(0.01),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('system.is_active'))
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label(__('system.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'size' => 'primary',
                        'smothing' => 'info',
                        'customize_modifires' => 'success',
                        'extra' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('max_level')
                    ->label(__('system.max_level'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('system.price'))
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('system.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('system.type'))
                    ->options([
                        'size' => __('system.size'),
                        'smothing' => __('system.smothing'),
                        'customize_modifires' => __('system.customize_modifires'),
                        'extra' => __('system.extra'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('system.is_active'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.active'))
                    ->falseLabel(__('system.inactive')),
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
            ->defaultSort('type', 'asc');
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
            'index' => Pages\ListModifiers::route('/'),
            'create' => Pages\CreateModifier::route('/create'),
            'view' => Pages\ViewModifier::route('/{record}'),
            'edit' => Pages\EditModifier::route('/{record}/edit'),
        ];
    }
}

