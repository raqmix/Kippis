<?php

namespace App\Filament\Resources\LoyaltyWalletResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label(__('system.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'earned' => 'success',
                        'redeemed' => 'warning',
                        'adjusted' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('points')
                    ->label(__('system.points'))
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '') . $state)
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('system.description'))
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_by')
                    ->label(__('system.created_by'))
                    ->formatStateUsing(fn ($state) => $state ? \App\Core\Models\Admin::find($state)?->name : '-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->heading('Transactions');
    }
}

