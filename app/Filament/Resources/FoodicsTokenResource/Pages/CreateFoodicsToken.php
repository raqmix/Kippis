<?php

namespace App\Filament\Resources\FoodicsTokenResource\Pages;

use App\Filament\Resources\FoodicsTokenResource;
use App\Integrations\Foodics\Models\FoodicsToken;
use App\Integrations\Foodics\Services\FoodicsAuthService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFoodicsToken extends CreateRecord
{
    protected static string $resource = FoodicsTokenResource::class;

    /**
     * Use FoodicsAuthService to store the token so existing tokens
     * for the same mode are replaced and expires_at is computed.
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var FoodicsAuthService $service */
        $service = app(FoodicsAuthService::class);

        return $service->storeToken(
            token: $data['access_token'],
            mode: $data['mode'],
            expiresIn: $data['expires_in'] ?? null,
        );
    }
}
