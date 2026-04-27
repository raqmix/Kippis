<?php

namespace App\Filament\Resources\CreatorDropResource\Pages;

use App\Filament\Resources\CreatorDropResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCreatorDrop extends EditRecord
{
    protected static string $resource = CreatorDropResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
