<?php

namespace App\Filament\Resources\RedeemItemResource\Pages;

use App\Filament\Resources\RedeemItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRedeemItem extends EditRecord
{
    protected static string $resource = RedeemItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
