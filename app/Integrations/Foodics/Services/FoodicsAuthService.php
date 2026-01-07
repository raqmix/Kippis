<?php

namespace App\Integrations\Foodics\Services;

use App\Integrations\Foodics\Exceptions\FoodicsUnauthorizedException;
use App\Integrations\Foodics\Models\FoodicsToken;
use App\Integrations\Foodics\FoodicsScopes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FoodicsAuthService
{
    /**
     * Get valid access token from database.
     *
     * @param string|null $mode 'sandbox' or 'live', null to use config default
     * @return string
     * @throws FoodicsUnauthorizedException
     */
    public function getAccessToken(?string $mode = null): string
    {
        $mode = $mode ?? config('foodics.mode', 'live');
        $token = FoodicsToken::getCurrent($mode);

        if (!$token) {
            throw new FoodicsUnauthorizedException("No token found for {$mode} mode. Please configure the token in Foodics Test page.");
        }

        if ($token->isExpired()) {
            throw new FoodicsUnauthorizedException("Token expired for {$mode} mode. Please update the token in Foodics Test page.");
        }

        return $token->access_token;
    }

    /**
     * Store a token in the database for a specific mode.
     *
     * @param string $token The bearer token
     * @param string $mode 'sandbox' or 'live'
     * @param int|null $expiresIn Optional expiration time in seconds
     * @return FoodicsToken
     */
    public function storeToken(string $token, string $mode, ?int $expiresIn = null): FoodicsToken
    {
        // Delete existing tokens for this mode
        FoodicsToken::where('mode', $mode)->delete();

        $expiresAt = null;
        if ($expiresIn) {
            $expiresAt = now()->addSeconds($expiresIn);
        }

        return FoodicsToken::create([
            'mode' => $mode,
            'access_token' => $token,
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Test API connection with stored token for a specific mode.
     *
     * @param string $mode 'sandbox' or 'live'
     * @return array
     */
    public function testAuthentication(string $mode): array
    {
        try {
            $token = $this->getAccessToken($mode);
            $baseUrl = $this->getBaseUrl($mode);

            // Test with a simple API call (e.g., get categories)
            $startTime = microtime(true);

            $response = Http::timeout(config('foodics.timeout', 30))
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get("{$baseUrl}/v5/categories", [
                    'per_page' => 1,
                ]);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->failed()) {
                $errorBody = $response->json();
                return [
                    'success' => false,
                    'message' => $errorBody['message'] ?? 'API request failed',
                    'error' => 'HTTP ' . $response->status(),
                    'status_code' => $response->status(),
                    'response_body' => $errorBody,
                    'duration_ms' => $duration,
                    'mode' => $mode,
                    'base_url' => $baseUrl,
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'message' => 'API connection successful',
                'token_preview' => substr($token, 0, 20) . '...',
                'duration_ms' => $duration,
                'mode' => $mode,
                'base_url' => $baseUrl,
                'response_data' => $data['data'] ?? [],
            ];
        } catch (FoodicsUnauthorizedException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Token error',
                'mode' => $mode,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Unexpected error',
                'mode' => $mode,
            ];
        }
    }

    /**
     * Get client ID for a specific mode.
     *
     * @param string $mode
     * @return string|null
     */
    private function getClientId(string $mode): ?string
    {
        if ($mode === 'sandbox') {
            return config('foodics.oauth.sandbox.client_id') ?: config('foodics.oauth.client_id');
        }

        return config('foodics.oauth.live.client_id') ?: config('foodics.oauth.client_id');
    }

    /**
     * Get client secret for a specific mode.
     *
     * @param string $mode
     * @return string|null
     */
    private function getClientSecret(string $mode): ?string
    {
        if ($mode === 'sandbox') {
            return config('foodics.oauth.sandbox.client_secret') ?: config('foodics.oauth.client_secret');
        }

        return config('foodics.oauth.live.client_secret') ?: config('foodics.oauth.client_secret');
    }

    /**
     * Get base URL for a specific mode.
     *
     * @param string $mode
     * @return string
     */
    private function getBaseUrl(string $mode): string
    {
        $baseUrls = config('foodics.base_urls', []);

        if (isset($baseUrls[$mode])) {
            return $baseUrls[$mode];
        }

        // Fallback to legacy config or default
        return config('foodics.base_url') ?: config('foodics.base_urls.live', 'https://api.foodics.com');
    }
}

