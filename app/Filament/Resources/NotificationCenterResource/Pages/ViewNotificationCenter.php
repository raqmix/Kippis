<?php

namespace App\Filament\Resources\NotificationCenterResource\Pages;

use App\Filament\Resources\NotificationCenterResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNotificationCenter extends ViewRecord
{
    protected static string $resource = NotificationCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

