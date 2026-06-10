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
        $canManage = fn () => \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_products');

        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sync_from_foodics')
                ->label(__('system.sync_from_foodics'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible($canManage)
                ->action(function () {
                    $service = app(\App\Core\Services\FoodicsSyncService::class);
                    $result = $service->syncProducts();
                    $this->renderSyncNotification($result);
                }),
            // Pull only the products in each branch's Foodics menu group.
            // New products land as drafts so they don't appear on the kiosk
            // / customer app until an operator activates them.
            Actions\Action::make('sync_store_menus')
                ->label('Sync store menus')
                ->icon('heroicon-o-building-storefront')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Pulls each branch\'s Foodics menu group. New products land as drafts.')
                ->visible($canManage)
                ->form([
                    \Filament\Forms\Components\Select::make('store_id')
                        ->label('Store')
                        ->options(fn () => \App\Core\Models\Store::query()
                            ->whereNotNull('foodics_menu_group_id')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->placeholder('— all stores —')
                        ->helperText('Leave blank to sync every branch that has a menu group set.'),
                ])
                ->action(function (array $data) {
                    $service = app(\App\Core\Services\FoodicsSyncService::class);
                    $storeId = $data['store_id'] ?? null;
                    if ($storeId) {
                        $store = \App\Core\Models\Store::findOrFail($storeId);
                        $result = $service->syncProductsForStore($store);
                    } else {
                        $result = $service->syncAllStoreMenus();
                    }
                    $this->renderSyncNotification($result);
                }),
        ];
    }

    private function renderSyncNotification(array $result): void
    {
        $hasErrors = !empty($result['errors']);
        $notification = \Filament\Notifications\Notification::make()
            ->title(__('system.sync_completed'))
            ->body(__('system.synced_count', ['count' => $result['synced']])
                . ' ' . __('system.updated_count', ['count' => $result['updated']])
                . ($hasErrors ? ' (' . count($result['errors']) . ' errors)' : ''));

        if (! $hasErrors) {
            $notification->success();
        } elseif (($result['synced'] + $result['updated']) > 0) {
            $notification->warning();
        } else {
            $notification->danger();
        }
        $notification->send();
    }
}

