<?php

namespace App\Filament\Resources;

use App\Core\Models\Frame;
use App\Filament\Resources\FrameResource\Pages;
use App\Filament\Resources\FrameResource\RelationManagers\RendersRelationManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class FrameResource extends Resource
{
    protected static ?string $model = Frame::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-photo';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_management');
    }

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('navigation.frames');
    }

    public static function getModelLabel(): string
    {
        return __('system.frame');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.frames');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_frames');
    }

    public static function canCreate(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_frames');
    }

    public static function canEdit($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_frames');
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_frames');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.frame_information'))
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
                                            ->maxLength(255),
                                    ]),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('thumbnail_path')
                            ->label(__('system.thumbnail'))
                            ->image()
                            ->directory('frames/thumbnails')
                            ->disk('public')
                            ->maxSize(2048)
                            ->imageEditor()
                            ->helperText(__('system.thumbnail_helper')),
                        Forms\Components\FileUpload::make('overlay_path')
                            ->label(__('system.overlay'))
                            ->image()
                            ->directory('frames/overlays')
                            ->disk('public')
                            ->acceptedFileTypes(['image/png'])
                            ->maxSize(5120)
                            ->required()
                            ->helperText(__('system.overlay_helper')),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('system.is_active'))
                            ->default(true)
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('system.sort_order'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label(__('system.starts_at'))
                            ->native(false),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label(__('system.ends_at'))
                            ->native(false)
                            ->after('starts_at'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_json')
                    ->label(__('system.name'))
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('thumbnail_path')
                    ->label(__('system.thumbnail'))
                    ->disk('public')
                    ->circular(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('system.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('system.sort_order'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('system.starts_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('system.ends_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('renders_count')
                    ->label(__('system.renders_count'))
                    ->counts('renders')
                    ->sortable(),
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
                Tables\Filters\Filter::make('expired')
                    ->label(__('system.expired'))
                    ->query(fn ($query) => $query->expired()),
                Tables\Filters\Filter::make('upcoming')
                    ->label(__('system.upcoming'))
                    ->query(fn ($query) => $query->where('starts_at', '>', now())),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order')
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RendersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrames::route('/'),
            'create' => Pages\CreateFrame::route('/create'),
            'edit' => Pages\EditFrame::route('/{record}/edit'),
            'view' => Pages\ViewFrame::route('/{record}'),
        ];
    }
}

