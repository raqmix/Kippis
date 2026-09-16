<?php

namespace App\Filament\Resources\SpendRewardTierResource\Pages;

use App\Filament\Resources\SpendRewardTierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSpendRewardTiers extends ListRecords
{
    protected static string $resource = SpendRewardTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
