<?php

namespace App\Filament\Resources\NotificationCenterResource\Pages;

use App\Filament\Resources\NotificationCenterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNotificationCenters extends ListRecords
{
    protected static string $resource = NotificationCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->label('إنشاء إشعار'),
        ];
    }
}

