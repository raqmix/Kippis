<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sync_from_foodics')
                ->label(__('system.sync_from_foodics'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories'))
                ->action(function () {
                    $service = app(\App\Core\Services\FoodicsSyncService::class);
                    $result = $service->syncCategories();
                    
                    $synced = $result['synced'] ?? 0;
                    $updated = $result['updated'] ?? 0;
                    $errors = $result['errors'] ?? [];
                    
                    if (!empty($errors)) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.sync_completed_with_errors'))
                            ->body(
                                __('system.synced_count', ['count' => $synced]) . ' ' . 
                                __('system.updated_count', ['count' => $updated]) . 
                                (count($errors) > 0 ? "\n\nErrors: " . implode('; ', array_slice($errors, 0, 3)) : '')
                            )
                            ->warning()
                            ->send();
                    } elseif ($synced === 0 && $updated === 0) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.sync_completed'))
                            ->body(__('system.no_categories_found'))
                            ->info()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.sync_completed'))
                            ->body(__('system.synced_count', ['count' => $synced]) . ' ' . __('system.updated_count', ['count' => $updated]))
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}

