<?php

namespace App\Filament\Pages;

use App\Core\Models\AdminLoginHistory;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class LoginHistory extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clock';
    }
    
    protected string $view = 'filament.pages.login-history';
    protected static ?int $navigationSort = 3;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.login_history');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.security');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AdminLoginHistory::query())
            ->columns([
                Tables\Columns\TextColumn::make('admin.name')
                    ->label(__('system.admin'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('admin.email')
                    ->label(__('system.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('login_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('logout_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('system.ip_address')),
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('login_at', 'desc');
    }
}

