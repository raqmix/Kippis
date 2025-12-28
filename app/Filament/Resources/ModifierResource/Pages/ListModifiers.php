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
            Actions\CreateAction::make(),
        ];
    }
}

