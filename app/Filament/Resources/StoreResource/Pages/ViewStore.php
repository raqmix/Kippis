<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStore extends ViewRecord
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
                    $apiKey = \Illuminate\Support\Str::uuid()->toString();
                    $this->record->update(['kiosk_api_key' => $apiKey]);
                    \Filament\Notifications\Notification::make()
                        ->title('API Key Generated Successfully')
                        ->body('New API key: ' . $apiKey . "\n\nCopy this key and use it in the X-Kiosk-API-Key header along with X-Store-ID: " . $this->record->id)
                        ->success()
                        ->persistent()
                        ->send();
                    $this->refreshFormData(['kiosk_api_key']);
                }),
            Actions\EditAction::make(),
        ];
    }
}

