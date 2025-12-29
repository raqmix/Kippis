<?php

namespace App\Filament\Resources\NotificationCenterResource\Pages;

use App\Filament\Resources\NotificationCenterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNotificationCenter extends EditRecord
{
    protected static string $resource = NotificationCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

