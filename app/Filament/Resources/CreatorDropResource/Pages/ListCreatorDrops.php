<?php

namespace App\Filament\Resources\CreatorDropResource\Pages;

use App\Filament\Resources\CreatorDropResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCreatorDrops extends ListRecords
{
    protected static string $resource = CreatorDropResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
