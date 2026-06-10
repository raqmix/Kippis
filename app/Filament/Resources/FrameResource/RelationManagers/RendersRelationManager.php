<?php

namespace App\Filament\Resources\FrameResource\RelationManagers;

use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

class RendersRelationManager extends RelationManager
{
    protected static string $relationship = 'renders';

    protected static ?string $title = 'Render History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Guest'),
                Tables\Columns\ImageColumn::make('rendered_image_path')
                    ->label('Rendered Image')
                    ->disk('public')
                    ->circular(),
                Tables\Columns\TextColumn::make('width')
                    ->label('Width')
                    ->sortable(),
                Tables\Columns\TextColumn::make('height')
                    ->label('Height')
                    ->sortable(),
                Tables\Columns\TextColumn::make('format')
                    ->label('Format')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No create action - renders are created via API only
            ])
            ->actions([
                // Read-only relation manager - no actions needed
            ])
            ->bulkActions([
                // No bulk actions - read-only
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true; // Always show render history
    }
}

