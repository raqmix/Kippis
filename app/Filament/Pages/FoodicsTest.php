<?php

namespace App\Filament\Pages;

use App\Integrations\Foodics\Models\FoodicsToken;
use App\Integrations\Foodics\Services\FoodicsAuthService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class FoodicsTest extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string $view = 'filament.pages.foodics-test';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('system.foodics_test');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.integrations');
    }

    public static function canAccess(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public ?string $testMode = 'sandbox';
    public ?array $sandboxResult = null;
    public ?array $liveResult = null;
    public bool $isTesting = false;

    public ?string $sandboxToken = null;
    public ?string $liveToken = null;

    public function mount(): void
    {
        $this->testMode = config('foodics.mode', 'live');

        // Load existing tokens
        $sandboxTokenRecord = FoodicsToken::where('mode', 'sandbox')->latest()->first();
        $liveTokenRecord = FoodicsToken::where('mode', 'live')->latest()->first();

        $this->sandboxToken = $sandboxTokenRecord?->access_token;
        $this->liveToken = $liveTokenRecord?->access_token;
    }

    public function testSandbox(): void
    {
        $this->isTesting = true;
        $this->sandboxResult = null;

        try {
            $authService = app(FoodicsAuthService::class);
            $this->sandboxResult = $authService->testAuthentication('sandbox');
        } catch (\Exception $e) {
            $this->sandboxResult = [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Exception occurred',
                'mode' => 'sandbox',
            ];
        } finally {
            $this->isTesting = false;
        }
    }

    public function testLive(): void
    {
        $this->isTesting = true;
        $this->liveResult = null;

        try {
            $authService = app(FoodicsAuthService::class);
            $this->liveResult = $authService->testAuthentication('live');
        } catch (\Exception $e) {
            $this->liveResult = [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Exception occurred',
                'mode' => 'live',
            ];
        } finally {
            $this->isTesting = false;
        }
    }

    public function testBoth(): void
    {
        $this->testSandbox();
        $this->testLive();
    }

    public function saveSandboxToken(): void
    {
        $this->validate([
            'sandboxToken' => 'required|string',
        ]);

        try {
            $authService = app(FoodicsAuthService::class);
            $authService->storeToken($this->sandboxToken, 'sandbox');

            // Reload token to show updated value
            $tokenRecord = FoodicsToken::where('mode', 'sandbox')->latest()->first();
            $this->sandboxToken = $tokenRecord?->access_token;

            notify()->success(
                __('system.token_saved_successfully'),
                __('system.sandbox_token_updated')
            );
        } catch (\Exception $e) {
            notify()->danger(
                __('system.failed_to_save_token'),
                $e->getMessage()
            );
        }
    }

    public function saveLiveToken(): void
    {
        $this->validate([
            'liveToken' => 'required|string',
        ]);

        try {
            $authService = app(FoodicsAuthService::class);
            $authService->storeToken($this->liveToken, 'live');

            // Reload token to show updated value
            $tokenRecord = FoodicsToken::where('mode', 'live')->latest()->first();
            $this->liveToken = $tokenRecord?->access_token;

            notify()->success(
                __('system.token_saved_successfully'),
                __('system.live_token_updated')
            );
        } catch (\Exception $e) {
            notify()->danger(
                __('system.failed_to_save_token'),
                $e->getMessage()
            );
        }
    }

    public function getSandboxConfigStatus(): array
    {
        $clientId = config('foodics.oauth.sandbox.client_id') ?: config('foodics.oauth.client_id');
        $clientSecret = config('foodics.oauth.sandbox.client_secret') ?: config('foodics.oauth.client_secret');
        $baseUrl = config('foodics.base_urls.sandbox', 'https://api-sandbox.foodics.com');

        return [
            'configured' => !empty($clientId) && !empty($clientSecret),
            'client_id_set' => !empty($clientId),
            'client_secret_set' => !empty($clientSecret),
            'base_url' => $baseUrl,
        ];
    }

    public function getLiveConfigStatus(): array
    {
        $clientId = config('foodics.oauth.live.client_id') ?: config('foodics.oauth.client_id');
        $clientSecret = config('foodics.oauth.live.client_secret') ?: config('foodics.oauth.client_secret');
        $baseUrl = config('foodics.base_urls.live', 'https://api.foodics.com');

        return [
            'configured' => !empty($clientId) && !empty($clientSecret),
            'client_id_set' => !empty($clientId),
            'client_secret_set' => !empty($clientSecret),
            'base_url' => $baseUrl,
        ];
    }
}

