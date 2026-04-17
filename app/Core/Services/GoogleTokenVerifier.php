<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleTokenVerifier
{
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    /**
     * Verify a Google access_token by fetching user info from Google's API.
     *
     * @param string $accessToken
     * @return array{sub: string, email: ?string, name: ?string, picture: ?string}
     * @throws \App\Http\Exceptions\ApiException
     */
    public function verify(string $accessToken): array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withToken($accessToken)
                ->get(self::USERINFO_URL);

            if (!$response->successful()) {
                throw new \App\Http\Exceptions\ApiException(
                    'INVALID_TOKEN',
                    'Invalid or expired Google token.',
                    401
                );
            }

            $data = $response->json();

            if (empty($data['sub'])) {
                throw new \App\Http\Exceptions\ApiException(
                    'INVALID_TOKEN',
                    'Google token did not return a user ID.',
                    401
                );
            }

            return $data;
        } catch (\App\Http\Exceptions\ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \App\Http\Exceptions\ApiException(
                'SOCIAL_AUTH_FAILED',
                'Failed to verify Google token.',
                401
            );
        }
    }
}
