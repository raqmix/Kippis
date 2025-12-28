<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sync_from_foodics')
                ->label(__('system.sync_from_foodics'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_products'))
                ->action(function () {
                    $service = app(\App\Core\Services\FoodicsSyncService::class);
                    $result = $service->syncProducts();
                    
                    \Filament\Notifications\Notification::make()
                        ->title(__('system.sync_completed'))
                        ->body(__('system.synced_count', ['count' => $result['synced']]) . ' ' . __('system.updated_count', ['count' => $result['updated']]))
                        ->success()
                        ->send();
                }),
        ];
    }
}

