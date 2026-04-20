<?php

namespace App\Core\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AppleTokenVerifier
{
    private const APPLE_KEYS_URL = 'https://appleid.apple.com/auth/keys';
    private const APPLE_ISSUER = 'https://appleid.apple.com';
    private const KEYS_CACHE_TTL = 3600; // 1 hour

    /**
     * Verify an Apple id_token and return decoded claims.
     *
     * @param string $idToken
     * @return array{sub: string, email: ?string, email_verified: ?string}
     * @throws \App\Http\Exceptions\ApiException
     */
    public function verify(string $idToken): array
    {
        try {
            $publicKeys = $this->getApplePublicKeys();

            // Allow 30 seconds of clock skew between client/server/Apple
            JWT::$leeway = 30;

            $decoded = JWT::decode($idToken, $publicKeys);
            $claims = (array) $decoded;

            // Validate issuer
            if (($claims['iss'] ?? '') !== self::APPLE_ISSUER) {
                throw new \App\Http\Exceptions\ApiException(
                    'INVALID_TOKEN',
                    'Invalid Apple token issuer.',
                    401
                );
            }

            // Validate audience matches our client ID
            $expectedClientId = config('services.apple.client_id');
            if (($claims['aud'] ?? '') !== $expectedClientId) {
                throw new \App\Http\Exceptions\ApiException(
                    'INVALID_TOKEN',
                    'Apple token audience mismatch.',
                    401
                );
            }

            return $claims;
        } catch (\App\Http\Exceptions\ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \App\Http\Exceptions\ApiException(
                'INVALID_TOKEN',
                'Invalid or expired Apple token.',
                401
            );
        }
    }

    /**
     * Fetch Apple's public JWKs (cached).
     */
    private function getApplePublicKeys(): array
    {
        $jwks = Cache::remember('apple:public-jwks', self::KEYS_CACHE_TTL, function () {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(10)->get(self::APPLE_KEYS_URL);

            if (!$response->successful()) {
                throw new \RuntimeException('Failed to fetch Apple public keys');
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks);
    }
}
