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
    public function getAccessToken(): string
    {
        $token = FoodicsToken::getCurrent();

        if ($token && !$token->isExpired()) {
            return $token->access_token;
        }

        return $this->refreshToken();
    }

    /**
     * Get new access token from Foodics using OAuth2.
     *
     * @return string
     * @throws FoodicsUnauthorizedException
     */
    public function refreshToken(): string
    {
        $clientId = config('foodics.oauth.client_id');
        $clientSecret = config('foodics.oauth.client_secret');
        $baseUrl = config('foodics.base_url', 'https://api.foodics.com');
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
}

