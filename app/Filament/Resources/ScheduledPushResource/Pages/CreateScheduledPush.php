<?php

namespace App\Filament\Resources\ScheduledPushResource\Pages;

use App\Filament\Resources\ScheduledPushResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduledPush extends CreateRecord
{
    protected static string $resource = ScheduledPushResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Stamp the admin who authored this row so the audit trail
        // survives even after that admin is deactivated.
        $data['created_by_admin_id'] = auth()->guard('admin')->id();
        return $data;
    }
}
