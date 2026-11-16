<?php

namespace App\Filament\Resources\PartnerCodeResource\Pages;

use App\Filament\Resources\PartnerCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePartnerCodes extends ManageRecords
{
    protected static string $resource = PartnerCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
