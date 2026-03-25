<?php

namespace App\Filament\Resources\FoodicsTokenResource\Pages;

use App\Filament\Resources\FoodicsTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFoodicsToken extends EditRecord
{
    protected static string $resource = FoodicsTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
