<?php

namespace App\Filament\Resources;

use App\Core\Models\ActivityLog;
use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;
    protected static ?int $navigationSort = 1;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.activity_logs');
    }

    public static function canCreate(): bool
    {
        return false; // Activity logs are read-only
    }

    public static function canEdit($record): bool
    {
        return false; // Activity logs are read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Activity logs are read-only
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.monitoring');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.activity_details'))
                    ->schema([
                        Forms\Components\Select::make('action')
                            ->options([
                                'create' => __('system.create'),
                                'update' => __('system.update'),
                                'delete' => __('system.delete'),
                                'login' => __('system.login'),
                                'logout' => __('system.logout'),
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('model_type')
                            ->disabled(),
                        Forms\Components\TextInput::make('model_id')
                            ->disabled(),
                        Forms\Components\KeyValue::make('old_values')
                            ->disabled(),
                        Forms\Components\KeyValue::make('new_values')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('admin.name')
                    ->label(__('system.admin'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge(),
                Tables\Columns\TextColumn::make('model_type')
                    ->label(__('system.model')),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'info' => 'gray',
                        'warning' => 'warning',
                        'error' => 'danger',
                        'critical' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('system.ip_address')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'create' => __('system.create'),
                        'update' => __('system.update'),
                        'delete' => __('system.delete'),
                        'login' => __('system.login'),
                        'logout' => __('system.logout'),
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}

