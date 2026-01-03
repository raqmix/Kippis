<?php

namespace App\Filament\Resources\PromoQrCodeResource\Pages;

use App\Filament\Resources\PromoQrCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromoQrCode extends CreateRecord
{
    protected static string $resource = PromoQrCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->guard('admin')->id();
        $data['total_uses_count'] = 0;

        return $data;
    }
}

