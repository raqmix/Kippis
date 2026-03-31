<?php

namespace App\Filament\Resources\ModifierResource\Pages;

use App\Filament\Resources\ModifierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListModifiers extends ListRecords
{
    protected static string $resource = ModifierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_from_foodics')
                ->label(__('system.sync_from_foodics'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_products'))
                ->action(function () {
                    $service = app(\App\Core\Services\FoodicsSyncService::class);
                    $result = $service->syncModifiers();

                    $hasErrors = !empty($result['errors']);

                    \Filament\Notifications\Notification::make()
                        ->title(__('system.sync_completed'))
                        ->body(__('system.synced_count', ['count' => $result['synced']]) . ' ' . __('system.updated_count', ['count' => $result['updated']]) . ($hasErrors ? ' (' . count($result['errors']) . ' errors)' : ''))
                        ->when(! $hasErrors, fn ($n) => $n->success())
                        ->when($hasErrors && ($result['synced'] + $result['updated']) > 0, fn ($n) => $n->warning())
                        ->when($hasErrors && ($result['synced'] + $result['updated']) === 0, fn ($n) => $n->danger())
                        ->send();
                }),
        ];
    }
}

