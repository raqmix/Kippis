<?php

namespace App\Filament\Resources;

use App\Core\Models\LoyaltyWallet;
use App\Filament\Resources\LoyaltyWalletResource\Pages;
use App\Filament\Resources\LoyaltyWalletResource\RelationManagers\TransactionsRelationManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class LoyaltyWalletResource extends Resource
{
    protected static ?string $model = LoyaltyWallet::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-wallet';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.customer_management');
    }

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('navigation.loyalty_wallets');
    }

    public static function getModelLabel(): string
    {
        return __('system.loyalty_wallet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.loyalty_wallets');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty');
    }

    public static function canCreate(): bool
    {
        return false; // Wallets are created automatically
    }

    public static function canEdit($record): bool
    {
        return false; // Wallets are read-only, points adjusted via actions
    }

    public static function canDelete($record): bool
    {
        return false; // Wallets cannot be deleted
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.loyalty_wallet_information'))
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label(__('system.customer'))
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('points')
                            ->label(__('system.points'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('system.customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->label(__('system.email'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('points')
                    ->label(__('system.points'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_transaction')
                    ->label(__('system.last_transaction'))
                    ->formatStateUsing(fn ($record) => $record->transactions()->latest()->first()?->created_at?->format('Y-m-d H:i'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('manual_adjustment')
                    ->label(__('system.manual_adjustment'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn () => Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty'))
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label(__('system.type'))
                            ->options([
                                'add' => __('system.add_points'),
                                'reduce' => __('system.reduce_points'),
                            ])
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('points')
                            ->label(__('system.points'))
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        Forms\Components\Textarea::make('description')
                            ->label(__('system.description'))
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (LoyaltyWallet $record, array $data) {
                        $points = (int) $data['points'];
                        $type = $data['type'] === 'add' ? 'adjusted' : 'adjusted';
                        $pointsValue = $data['type'] === 'add' ? $points : -$points;

                        $record->addPoints(
                            $pointsValue,
                            $type,
                            $data['description'],
                            null,
                            null,
                            auth()->guard('admin')->id()
                        );

                        \Filament\Notifications\Notification::make()
                            ->title(__('system.points_adjusted'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('points', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyWallets::route('/'),
            'view' => Pages\ViewLoyaltyWallet::route('/{record}'),
        ];
    }
}

