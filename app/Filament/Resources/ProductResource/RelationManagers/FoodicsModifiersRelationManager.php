<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FoodicsModifiersRelationManager extends RelationManager
{
    protected static string $relationship = 'foodicsModifiers';

    protected static ?string $title = 'Foodics Modifiers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Modifier Group')
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable(query: fn ($query, string $search) =>
                        $query->where('name_json->en', 'like', "%{$search}%")
                              ->orWhere('name_json->ar', 'like', "%{$search}%")
                    ),
                Tables\Columns\TextColumn::make('pivot.minimum_options')
                    ->label('Min')
                    ->numeric(),
                Tables\Columns\TextColumn::make('pivot.maximum_options')
                    ->label('Max')
                    ->numeric(),
                Tables\Columns\TextColumn::make('pivot.free_options')
                    ->label('Free')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pivot.sort_order')
                    ->label('Sort')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('options_count')
                    ->label('Options')
                    ->counts('options'),
            ])
            ->paginated(false);
    }
}
