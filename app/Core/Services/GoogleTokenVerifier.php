<?php

namespace App\Core\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/// Verifies a Google **id_token** (JWT) against Google's JWKs, mirroring
/// AppleTokenVerifier. The previous implementation called Google's
/// /oauth2/v3/userinfo with the client-supplied access_token — but that
/// endpoint accepts tokens minted for ANY OAuth client, which made the
/// social-login route a one-shot account-takeover primitive.
///
/// The id_token JWT, by contrast, has its `aud` claim baked in by Google
/// against the requesting OAuth client. As long as we check the
/// signature, issuer, and audience against our own client IDs, only
/// tokens minted FOR Kippis can authenticate. The Flutter side gets
/// this `id_token` from google_sign_in's `serverClientId`-issued
/// auth blob.
class GoogleTokenVerifier
{
    private const GOOGLE_KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const KEYS_CACHE_TTL = 3600; // 1 hour
    private const VALID_ISSUERS = [
        'accounts.google.com',
        'https://accounts.google.com',
    ];

    /**
     * Verify a Google id_token and return decoded claims.
     *
     * @return array{sub: string, email: ?string, email_verified: ?bool, name: ?string, picture: ?string}
     * @throws \App\Http\Exceptions\ApiException
     */
    public function verify(string $idToken): array
    {
        try {
            $publicKeys = $this->getGooglePublicKeys();

            // 30s of clock skew between client/server/Google
            JWT::$leeway = 30;

            $decoded = JWT::decode($idToken, $publicKeys);
            $claims = (array) $decoded;

            // Issuer must be Google
            if (!in_array($claims['iss'] ?? '', self::VALID_ISSUERS, true)) {
                throw new \App\Http\Exceptions\ApiException(
                    'INVALID_TOKEN',
                    'Invalid Google token issuer.',
                    401
                );
            }

            // Audience must be one of Kippis's own OAuth client IDs.
            // Google issues separate IDs per platform (web/android/ios)
            // and the id_token's `aud` is whichever client requested it.
            $allowedAudiences = $this->allowedAudiences();
            if (empty($allowedAudiences)) {
                throw new \App\Http\Exceptions\ApiException(
                    'SOCIAL_AUTH_MISCONFIGURED',
                    'Google client IDs are not configured server-side.',
                    500
                );
            }
            if (!in_array($claims['aud'] ?? '', $allowedAudiences, true)) {
                throw new \App\Http\Exceptions\ApiException(
                    'INVALID_TOKEN',
                    'Google token audience mismatch.',
                    401
                );
            }

            if (empty($claims['sub'])) {
                throw new \App\Http\Exceptions\ApiException(
                    'INVALID_TOKEN',
                    'Google token has no subject.',
                    401
                );
            }

            return $claims;
        } catch (\App\Http\Exceptions\ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \App\Http\Exceptions\ApiException(
                'INVALID_TOKEN',
                'Invalid or expired Google token.',
                401
            );
        }
    }

    /**
     * Allowed `aud` values for an id_token to be accepted.
     *
     * Supports `services.google.client_id` (legacy single value),
     * `services.google.client_ids` (array OR comma-separated string),
     * and convenience-typed per-platform overrides.
     *
     * @return array<int, string>
     */
    private function allowedAudiences(): array
    {
        $ids = [];

        $single = config('services.google.client_id');
        if (is_string($single) && $single !== '') {
            $ids[] = $single;
        }

        $multi = config('services.google.client_ids');
        if (is_array($multi)) {
            $ids = array_merge($ids, $multi);
        } elseif (is_string($multi) && $multi !== '') {
            $ids = array_merge(
                $ids,
                array_filter(array_map('trim', explode(',', $multi)))
            );
        }

        foreach (['web', 'android', 'ios'] as $platform) {
            $platformId = config("services.google.client_id_{$platform}");
            if (is_string($platformId) && $platformId !== '') {
                $ids[] = $platformId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Fetch and cache Google's public JWKs.
     */
    private function getGooglePublicKeys(): array
    {
        $jwks = Cache::remember('google:public-jwks', self::KEYS_CACHE_TTL, function () {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(10)->get(self::GOOGLE_KEYS_URL);

            if (!$response->successful()) {
                throw new \RuntimeException('Failed to fetch Google public keys');
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks);
    }
}
