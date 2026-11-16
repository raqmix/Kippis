<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_kiosk_api_key')
                ->label('Generate Kiosk API Key')
                ->icon('heroicon-o-key')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generate Kiosk API Key')
                ->modalDescription('This will generate a new API key for this store. The old key (if any) will be replaced.')
                ->action(function () {
                    $apiKey = $this->record->generateKioskApiKey();
                    \Filament\Notifications\Notification::make()
                        ->title('API Key Generated Successfully')
                        ->body('New API key (copy it now — it is stored hashed and cannot be shown again): ' . $apiKey . "\n\nUse it in the X-Kiosk-API-Key header along with X-Store-ID: " . $this->record->id)
                        ->success()
                        ->persistent()
                        ->send();
                    $this->refreshFormData(['kiosk_api_key']);
                }),
            Actions\Action::make('use_foodics_name')
                ->label('Use Foodics name again')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('The next Foodics branch sync will overwrite this store\'s name with the Foodics one.')
                ->visible(fn () => $this->record->name_edited_at !== null && $this->record->foodics_branch_id)
                ->action(function () {
                    $this->record->update(['name_edited_at' => null]);
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Clean up empty values from name_localized
        if (isset($data['name_localized']) && is_array($data['name_localized'])) {
            $data['name_localized'] = array_filter($data['name_localized'], fn($value) => !empty($value));
            $data['name_localized'] = !empty($data['name_localized']) ? $data['name_localized'] : null;
        }

        // An admin changing the name makes it theirs: the Foodics sync stops
        // overwriting it (name_edited_at). Saving other fields leaves it alone.
        $normalize = function ($localized) {
            $localized = array_filter((array) $localized, fn ($v) => $v !== null && $v !== '');
            ksort($localized);

            return $localized;
        };
        if (
            ($data['name'] ?? $this->record->name) !== $this->record->name
            || $normalize($data['name_localized'] ?? null) !== $normalize($this->record->name_localized)
        ) {
            $data['name_edited_at'] = now();
        }

        return $data;
    }
}

