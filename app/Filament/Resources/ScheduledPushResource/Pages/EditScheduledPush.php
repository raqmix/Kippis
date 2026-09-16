<?php

namespace App\Filament\Resources\ScheduledPushResource\Pages;

use App\Filament\Resources\ScheduledPushResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScheduledPush extends EditRecord
{
    protected static string $resource = ScheduledPushResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
