<?php

namespace App\Filament\Resources\SpendRewardTierResource\Pages;

use App\Filament\Resources\SpendRewardTierResource;
use App\Support\Money;
use Filament\Resources\Pages\CreateRecord;

class CreateSpendRewardTier extends CreateRecord
{
    protected static string $resource = SpendRewardTierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert EGP → piasters at the boundary; the DB stores piasters
        // to stay consistent with orders.total * 100 and
        // customers.lifetime_spent_piasters.
        if (isset($data['threshold_egp'])) {
            $data['threshold_piasters'] = Money::toPiasters((float) $data['threshold_egp']);
            unset($data['threshold_egp']);
        }
        return $data;
    }
}
