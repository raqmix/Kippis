<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_status')
                ->label(__('system.update_status'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders'))
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->label(__('system.status'))
                        ->options([
                            'received' => __('system.received'),
                            'mixing' => __('system.mixing'),
                            'ready' => __('system.ready'),
                            'completed' => __('system.completed'),
                            'cancelled' => __('system.cancelled'),
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update(['status' => $data['status']]);
                    \Filament\Notifications\Notification::make()
                        ->title(__('system.status_updated'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

