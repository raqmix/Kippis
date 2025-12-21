<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Clean up empty values from name_localized
        if (isset($data['name_localized']) && is_array($data['name_localized'])) {
            $data['name_localized'] = array_filter($data['name_localized'], fn($value) => !empty($value));
            $data['name_localized'] = !empty($data['name_localized']) ? $data['name_localized'] : null;
        }

        return $data;
    }
}

