<?php

namespace App\Filament\Resources\EventRequestResource\Pages;

use App\Filament\Resources\EventRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventRequest extends EditRecord
{
    protected static string $resource = EventRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
