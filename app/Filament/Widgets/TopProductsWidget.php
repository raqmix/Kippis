<?php

namespace App\Filament\Widgets;

use App\Core\Models\Order;
use App\Core\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

class TopProductsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static ?string $heading = null;

    public function table(Table $table): Table
    {
        // Calculate top products from order items
        $productStats = $this->calculateProductStats();
        $productIds = $productStats->pluck('product_id')->toArray();

        if (empty($productIds)) {
            // Return empty query if no products
            return $table
                ->query(Product::query()->whereRaw('1 = 0'))
                ->columns([
                    Tables\Columns\TextColumn::make('name')
                        ->label(__('system.name')),
                ])
                ->heading(__('system.top_products'))
                ->emptyStateHeading(__('system.no_data'));
        }

        return $table
            ->query(
                Product::query()
                    ->whereIn('id', $productIds)
                    ->orderByRaw('FIELD(id, ' . implode(',', $productIds) . ')')
            )
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label(__('system.image'))
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('system.name'))
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_sold')
                    ->label(__('system.total_sold'))
                    ->getStateUsing(fn ($record) => $productStats->firstWhere('product_id', $record->id)['total_sold'] ?? 0)
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label(__('system.total_revenue'))
                    ->getStateUsing(fn ($record) => $productStats->firstWhere('product_id', $record->id)['total_revenue'] ?? 0)
                    ->money('SAR')
                    ->sortable(),
            ])
            ->heading(__('system.top_products'));
    }

    protected function calculateProductStats(): Collection
    {
        $stats = collect();

        Order::where('status', '!=', 'cancelled')
            ->get()
            ->each(function ($order) use ($stats) {
                if (is_array($order->items_snapshot)) {
                    foreach ($order->items_snapshot as $item) {
                        if (isset($item['product_id'])) {
                            $productId = $item['product_id'];
                            $quantity = $item['quantity'] ?? 0;
                            $price = $item['price'] ?? 0;

                            if (!$stats->has($productId)) {
                                $stats->put($productId, [
                                    'product_id' => $productId,
                                    'total_sold' => 0,
                                    'total_revenue' => 0,
                                ]);
                            }

                            $current = $stats->get($productId);
                            $current['total_sold'] += $quantity;
                            $current['total_revenue'] += $price * $quantity;
                            $stats->put($productId, $current);
                        }
                    }
                }
            });

        return $stats->sortByDesc('total_sold')->take(10)->values();
    }
}
