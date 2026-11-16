<?php

namespace App\Filament\Resources\PointsMultiplierRuleResource\Pages;

use App\Filament\Resources\PointsMultiplierRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPointsMultiplierRules extends ListRecords
{
    protected static string $resource = PointsMultiplierRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
