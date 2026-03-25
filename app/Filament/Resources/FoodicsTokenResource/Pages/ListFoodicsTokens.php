<?php

namespace App\Filament\Resources\FoodicsTokenResource\Pages;

use App\Filament\Resources\FoodicsTokenResource;
use App\Integrations\Foodics\Services\FoodicsAuthService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFoodicsTokens extends ListRecords
{
    protected static string $resource = FoodicsTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Token'),

            Actions\Action::make('test_sandbox')
                ->label('Test Sandbox')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function (): void {
                    try {
                        $result = app(FoodicsAuthService::class)->testAuthentication('sandbox');

                        if ($result['success']) {
                            Notification::make()
                                ->title('Sandbox: Connection Successful')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sandbox: Connection Failed')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sandbox Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('test_live')
                ->label('Test Live')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->action(function (): void {
                    try {
                        $result = app(FoodicsAuthService::class)->testAuthentication('live');

                        if ($result['success']) {
                            Notification::make()
                                ->title('Live: Connection Successful')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Live: Connection Failed')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Live Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
