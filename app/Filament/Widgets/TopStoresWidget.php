<?php

namespace App\Filament\Widgets;

use App\Core\Models\Order;
use App\Core\Models\Store;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopStoresWidget extends BaseWidget
{
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static ?int $sort = 7;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Store::query()
                    ->select('stores.*')
                    ->selectSub(function ($query) {
                        $query->selectRaw('COUNT(*)')
                            ->from('orders')
                            ->whereColumn('orders.store_id', 'stores.id')
                            ->where('orders.status', '!=', 'cancelled');
                    }, 'total_orders')
                    ->selectSub(function ($query) {
                        $query->selectRaw('COALESCE(SUM(total), 0)')
                            ->from('orders')
                            ->whereColumn('orders.store_id', 'stores.id')
                            ->where('orders.status', '!=', 'cancelled');
                    }, 'total_revenue')
                    ->orderByDesc('total_revenue')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('system.store'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_orders')
                    ->label(__('system.total_orders'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label(__('system.total_revenue'))
                    ->money('EGP')
                    ->sortable(),
            ])
            ->defaultSort('total_revenue', 'desc')
            ->heading(__('system.top_stores'));
    }
}

