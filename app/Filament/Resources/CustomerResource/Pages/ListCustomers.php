<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Core\Models\Customer;
use App\Core\Services\FcmService;
use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send_test_fcm')
                ->label('Send test FCM')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->action(function (): void {
                    $customer = Customer::whereNotNull('fcm_token')->first();
                    if (!$customer) {
                        Notification::make()
                            ->title('No FCM token')
                            ->body('No customer has registered an FCM token yet.')
                            ->warning()
                            ->send();
                        return;
                    }
                    try {
                        app(FcmService::class)->sendToToken(
                            $customer->fcm_token,
                            'Test',
                            'Dummy notification from admin'
                        );
                        Notification::make()
                            ->title('FCM sent')
                            ->body('Test notification sent to ' . $customer->name . '.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('FCM failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->withTrashed();
    }
}

