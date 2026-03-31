<?php

namespace App\Filament\Resources\ModifierResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Options';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable(query: fn ($query, string $search) =>
                        $query->where('name_json->en', 'like', "%{$search}%")
                              ->orWhere('name_json->ar', 'like', "%{$search}%")
                    ),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('calories')
                    ->label('Calories')
                    ->numeric()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->paginated(false);
    }
}
