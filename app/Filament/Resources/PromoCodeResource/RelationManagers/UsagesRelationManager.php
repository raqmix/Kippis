<?php

namespace App\Filament\Resources\PromoCodeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
                    ->label(__('system.customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->label(__('system.email'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->label(__('system.order_id'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label(__('system.discount_amount'))
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('used_at')
                    ->label(__('system.used_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('used_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('used_from')
                            ->label(__('system.from')),
                        \Filament\Forms\Components\DatePicker::make('used_to')
                            ->label(__('system.to')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['used_from'], fn ($q, $date) => $q->where('used_at', '>=', $date))
                            ->when($data['used_to'], fn ($q, $date) => $q->where('used_at', '<=', $date));
                    }),
            ])
            ->defaultSort('used_at', 'desc')
            ->heading('Usage History');
    }
}

