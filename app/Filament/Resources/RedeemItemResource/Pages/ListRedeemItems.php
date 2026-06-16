<?php

namespace App\Filament\Resources\RedeemItemResource\Pages;

use App\Filament\Resources\RedeemItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRedeemItems extends ListRecords
{
    protected static string $resource = RedeemItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
