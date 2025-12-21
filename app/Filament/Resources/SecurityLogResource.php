<?php

namespace App\Filament\Resources;

use App\Core\Models\SecurityLog;
use App\Filament\Resources\SecurityLogResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SecurityLogResource extends Resource
{
    protected static ?string $model = SecurityLog::class;
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-exclamation';
    }

    public static function canCreate(): bool
    {
        return false; // Security logs are read-only
    }

    public static function canEdit($record): bool
    {
        return false; // Security logs are read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Security logs are read-only
    }
    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.security');
    }
    protected static ?int $navigationSort = 2;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.security_logs');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.event_details'))
                    ->schema([
                        Forms\Components\Select::make('severity')
                            ->options([
                                'low' => __('system.low'),
                                'medium' => __('system.medium'),
                                'high' => __('system.high'),
                                'critical' => __('system.critical'),
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('event_type')
                            ->disabled(),
                        Forms\Components\Textarea::make('description')
                            ->disabled()
                            ->rows(3),
                        Forms\Components\KeyValue::make('metadata')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('admin.name')
                    ->label(__('system.admin'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('system.ip_address')),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\IconColumn::make('resolved')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'low' => __('system.low'),
                        'medium' => __('system.medium'),
                        'high' => __('system.high'),
                        'critical' => __('system.critical'),
                    ]),
                Tables\Filters\TernaryFilter::make('resolved')
                    ->label(__('system.resolved')),
            ])
            ->actions([
                Actions\Action::make('resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (SecurityLog $record) => $record->resolve(auth('admin')->user()))
                    ->visible(fn (SecurityLog $record) => !$record->resolved),
                Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityLogs::route('/'),
            'view' => Pages\ViewSecurityLog::route('/{record}'),
        ];
    }
}

