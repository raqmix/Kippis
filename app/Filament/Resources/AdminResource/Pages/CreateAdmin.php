<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    protected function afterCreate(): void
    {
        $roles = $this->data['roles'] ?? [];

        if (!empty($roles)) {
            $this->record->syncRoles($roles);
        }
    }
}

