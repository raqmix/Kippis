<?php

namespace App\Filament\Resources;

use App\Core\Models\Order;
use App\Filament\Resources\OrderResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_management');
    }

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('navigation.orders');
    }

    public static function getModelLabel(): string
    {
        return __('system.order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.orders');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders');
    }

    public static function canCreate(): bool
    {
        return false; // Orders are created via API only
    }

    public static function canEdit($record): bool
    {
        return false; // Orders are read-only, status updated via actions
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.order_information'))
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label(__('system.status'))
                            ->options([
                                'received' => __('system.received'),
                                'mixing' => __('system.mixing'),
                                'ready' => __('system.ready'),
                                'completed' => __('system.completed'),
                                'cancelled' => __('system.cancelled'),
                            ])
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('pickup_code')
                            ->label(__('system.pickup_code'))
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('total')
                            ->label(__('system.total'))
                            ->prefix('SAR')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('system.order_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('system.customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('system.store'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('system.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'info',
                        'mixing' => 'warning',
                        'ready' => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label(__('system.total'))
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label(__('system.payment_method'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pickup_code')
                    ->label(__('system.pickup_code'))
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label(__('system.store'))
                    ->relationship('store', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('system.status'))
                    ->options([
                        'received' => __('system.received'),
                        'mixing' => __('system.mixing'),
                        'ready' => __('system.ready'),
                        'completed' => __('system.completed'),
                        'cancelled' => __('system.cancelled'),
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label(__('system.payment_method'))
                    ->options([
                        'cash' => __('system.cash'),
                        'card' => __('system.card'),
                        'online' => __('system.online'),
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('system.from')),
                        Forms\Components\DatePicker::make('created_to')
                            ->label(__('system.to')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_to'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\Filter::make('total')
                    ->form([
                        Forms\Components\TextInput::make('total_from')
                            ->label(__('system.from'))
                            ->numeric(),
                        Forms\Components\TextInput::make('total_to')
                            ->label(__('system.to'))
                            ->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['total_from'], fn ($q, $amount) => $q->where('total', '>=', $amount))
                            ->when($data['total_to'], fn ($q, $amount) => $q->where('total', '<=', $amount));
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('update_status')
                    ->label(__('system.update_status'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders'))
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label(__('system.status'))
                            ->options([
                                'received' => __('system.received'),
                                'mixing' => __('system.mixing'),
                                'ready' => __('system.ready'),
                                'completed' => __('system.completed'),
                                'cancelled' => __('system.cancelled'),
                            ])
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update(['status' => $data['status']]);
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.status_updated'))
                            ->success()
                            ->send();
                    }),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),
                Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}

