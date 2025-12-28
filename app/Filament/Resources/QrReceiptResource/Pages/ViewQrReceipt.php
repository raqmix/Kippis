<?php

namespace App\Filament\Resources\QrReceiptResource\Pages;

use App\Filament\Resources\QrReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQrReceipt extends ViewRecord
{
    protected static string $resource = QrReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label(__('system.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'pending' && \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_qr_receipts'))
                ->form([
                    \Filament\Forms\Components\TextInput::make('points_awarded')
                        ->label(__('system.points_awarded'))
                        ->numeric()
                        ->required()
                        ->minValue(1),
                ])
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'approved',
                        'points_awarded' => $data['points_awarded'],
                        'approved_at' => now(),
                        'approved_by' => auth()->guard('admin')->id(),
                    ]);

                    $wallet = $this->record->customer->loyaltyWallet;
                    if ($wallet) {
                        $wallet->addPoints(
                            $data['points_awarded'],
                            'earned',
                            "Points from receipt #{$this->record->receipt_number}",
                            'qr_receipt',
                            $this->record->id
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
                ->visible(fn () => $this->record->status === 'pending' && \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_qr_receipts'))
                ->action(function () {
                    $this->record->update([
                        'status' => 'rejected',
                        'approved_by' => auth()->guard('admin')->id(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title(__('system.receipt_rejected'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

