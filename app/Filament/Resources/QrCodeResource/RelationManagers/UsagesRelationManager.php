<?php

namespace App\Filament\Resources\QrCodeResource\RelationManagers;

use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'Usage History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('used_at')
                    ->label('Used At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qrCode.points_awarded')
                    ->label('Points Awarded')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No create action - usages are created via API only
            ])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions - read-only
            ])
            ->defaultSort('used_at', 'desc');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true; // Always show usage history
    }
}
