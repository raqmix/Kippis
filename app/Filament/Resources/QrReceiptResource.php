<?php

namespace App\Filament\Resources;

use App\Core\Models\QrReceipt;
use App\Filament\Resources\QrReceiptResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class QrReceiptResource extends Resource
{
    protected static ?string $model = QrReceipt::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-qr-code';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.customer_management');
    }

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('navigation.qr_receipts');
    }

    public static function getModelLabel(): string
    {
        return __('system.qr_receipt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.qr_receipts');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_qr_receipts');
    }

    public static function canCreate(): bool
    {
        return false; // Receipts are created via API only
    }

    public static function canEdit($record): bool
    {
        return false; // Receipts are read-only, approved/rejected via actions
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_qr_receipts');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.receipt_information'))
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label(__('system.customer'))
                            ->relationship('customer', 'name')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('receipt_number')
                            ->label(__('system.receipt_number'))
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('amount')
                            ->label(__('system.amount'))
                            ->prefix('SAR')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Select::make('status')
                            ->label(__('system.status'))
                            ->options([
                                'pending' => __('system.pending'),
                                'approved' => __('system.approved'),
                                'rejected' => __('system.rejected'),
                            ])
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('points_requested')
                            ->label(__('system.points_requested'))
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('points_awarded')
                            ->label(__('system.points_awarded'))
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),
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
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label(__('system.receipt_number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('system.amount'))
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('system.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('points_awarded')
                    ->label(__('system.points_awarded'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('scanned_at')
                    ->label(__('system.scanned_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('system.status'))
                    ->options([
                        'pending' => __('system.pending'),
                        'approved' => __('system.approved'),
                        'rejected' => __('system.rejected'),
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
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('system.customer'))
                    ->relationship('customer', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label(__('system.store'))
                    ->relationship('store', 'name')
                    ->searchable(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('approve')
                    ->label(__('system.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending' && Gate::forUser(auth()->guard('admin')->user())->allows('manage_qr_receipts'))
                    ->form([
                        Forms\Components\TextInput::make('points_awarded')
                            ->label(__('system.points_awarded'))
                            ->numeric()
                            ->required()
                            ->minValue(1),
                    ])
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function (QrReceipt $record, array $data) {
                        $record->update([
                            'status' => 'approved',
                            'points_awarded' => $data['points_awarded'],
                            'approved_at' => now(),
                            'approved_by' => auth()->guard('admin')->id(),
                        ]);

                        // Award points to customer's loyalty wallet
                        $wallet = $record->customer->loyaltyWallet;
                        if ($wallet) {
                            $wallet->addPoints(
                                $data['points_awarded'],
                                'earned',
                                "Points from receipt #{$record->receipt_number}",
                                'qr_receipt',
                                $record->id
                            );
                        }

                        \Filament\Notifications\Notification::make()
                            ->title(__('system.receipt_approved'))
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('reject')
                    ->label(__('system.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending' && Gate::forUser(auth()->guard('admin')->user())->allows('manage_qr_receipts'))
                    ->action(function (QrReceipt $record) {
                        $record->update([
                            'status' => 'rejected',
                            'approved_by' => auth()->guard('admin')->id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title(__('system.receipt_rejected'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQrReceipts::route('/'),
            'view' => Pages\ViewQrReceipt::route('/{record}'),
        ];
    }
}

