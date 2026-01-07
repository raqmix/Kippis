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
     * Get valid access token (refresh if needed).
     *
     * @return string
     * @throws FoodicsUnauthorizedException
     */
    public function getAccessToken(?string $mode = null): string
    {
        $token = FoodicsToken::getCurrent();

        if ($token && !$token->isExpired()) {
            return $token->access_token;
        }

        return $this->refreshToken($mode);
    }

    /**
     * Get new access token from Foodics using OAuth2.
     *
     * @param string|null $mode 'sandbox' or 'live', null to use config default
     * @return string
     * @throws FoodicsUnauthorizedException
     */
    public function refreshToken(?string $mode = null): string
    {
        $mode = $mode ?? config('foodics.mode', 'live');
        $clientId = $this->getClientId($mode);
        $clientSecret = $this->getClientSecret($mode);
        $baseUrl = $this->getBaseUrl($mode);
        $grantType = config('foodics.oauth.grant_type', 'client_credentials');
        $scopes = FoodicsScopes::required();

        if (!$clientId || !$clientSecret) {
            throw new FoodicsUnauthorizedException('Foodics credentials not configured.');
        }

        try {
            $payload = [
                'grant_type' => $grantType,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ];

            // Add scopes if configured
            if (!empty($scopes)) {
                $payload['scope'] = implode(' ', $scopes);
            }

            $response = Http::timeout(config('foodics.timeout', 30))
                ->post("{$baseUrl}/oauth/token", $payload);

            if ($response->failed()) {
                Log::error('Foodics token refresh failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new FoodicsUnauthorizedException('Failed to authenticate with Foodics.');
            }

            $data = $response->json();

            // Store token (replace old tokens)
            FoodicsToken::truncate();

            $expiresIn = $data['expires_in'] ?? 3600;
            FoodicsToken::create([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_in' => $expiresIn,
                'expires_at' => now()->addSeconds($expiresIn),
                'token_type' => $data['token_type'] ?? 'Bearer',
            ]);

            return $data['access_token'];
        } catch (FoodicsUnauthorizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Foodics token refresh exception', [
                'error' => $e->getMessage(),
            ]);

            throw new FoodicsUnauthorizedException('Failed to authenticate with Foodics: ' . $e->getMessage());
        }
    }

    /**
     * Test authentication with a specific mode (without storing token).
     *
     * @param string $mode 'sandbox' or 'live'
     * @return array
     */
    public function testAuthentication(string $mode): array
    {
        try {
            $clientId = $this->getClientId($mode);
            $clientSecret = $this->getClientSecret($mode);
            $baseUrl = $this->getBaseUrl($mode);
            $grantType = config('foodics.oauth.grant_type', 'client_credentials');
            $scopes = FoodicsScopes::required();

            if (!$clientId || !$clientSecret) {
                return [
                    'success' => false,
                    'message' => 'Credentials not configured for ' . $mode . ' mode',
                    'error' => 'Missing credentials',
                    'mode' => $mode,
                    'base_url' => $baseUrl,
                ];
            }

            $startTime = microtime(true);

            $payload = [
                'grant_type' => $grantType,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ];

            if (!empty($scopes)) {
                $payload['scope'] = implode(' ', $scopes);
            }

            $response = Http::timeout(config('foodics.timeout', 30))
                ->post("{$baseUrl}/oauth/token", $payload);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->failed()) {
                $errorBody = $response->json();
                return [
                    'success' => false,
                    'message' => $errorBody['message'] ?? 'Authentication failed',
                    'error' => 'HTTP ' . $response->status(),
                    'status_code' => $response->status(),
                    'response_body' => $errorBody,
                    'duration_ms' => $duration,
                    'mode' => $mode,
                    'base_url' => $baseUrl,
                ];
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;

            return [
                'success' => true,
                'message' => 'Authentication successful',
                'token_preview' => $token ? (substr($token, 0, 20) . '...') : 'N/A',
                'token_type' => $data['token_type'] ?? 'Bearer',
                'expires_in' => $data['expires_in'] ?? null,
                'duration_ms' => $duration,
                'mode' => $mode,
                'base_url' => $baseUrl,
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

