<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use App\Modules\Stores\Services\FoodicsBranchesSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncBranches')
                ->label(__('system.sync_branches'))
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action(function () {
                    try {
                        $syncService = app(FoodicsBranchesSyncService::class);
                        $stats = $syncService->syncAllBranches();

                        Notification::make()
                            ->title(__('system.sync_branches_success'))
                            ->body(__('system.sync_branches_success_body', [
                                'created' => $stats['created'],
                                'updated' => $stats['updated'],
                                'skipped' => $stats['skipped'],
                            ]))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('system.sync_branches_failed'))
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

