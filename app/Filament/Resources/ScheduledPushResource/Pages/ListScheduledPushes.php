<?php

namespace App\Filament\Resources\ScheduledPushResource\Pages;

use App\Filament\Resources\ScheduledPushResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScheduledPushes extends ListRecords
{
    protected static string $resource = ScheduledPushResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
