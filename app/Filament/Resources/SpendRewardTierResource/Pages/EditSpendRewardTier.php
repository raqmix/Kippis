<?php

namespace App\Filament\Resources\SpendRewardTierResource\Pages;

use App\Filament\Resources\SpendRewardTierResource;
use App\Support\Money;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSpendRewardTier extends EditRecord
{
    protected static string $resource = SpendRewardTierResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Fill the EGP text field from the piasters column so the
        // admin sees the friendly value.
        if (isset($data['threshold_piasters'])) {
            $data['threshold_egp'] = Money::piastersToDecimalString((int) $data['threshold_piasters']);
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['threshold_egp'])) {
            $data['threshold_piasters'] = Money::toPiasters((float) $data['threshold_egp']);
            unset($data['threshold_egp']);
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
