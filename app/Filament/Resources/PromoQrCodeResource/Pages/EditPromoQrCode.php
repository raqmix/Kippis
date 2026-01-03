<?php

namespace App\Filament\Resources\PromoQrCodeResource\Pages;

use App\Filament\Resources\PromoQrCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromoQrCode extends EditRecord
{
    protected static string $resource = PromoQrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

