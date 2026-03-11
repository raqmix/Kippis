<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\Http;

class FcmService
{
    private ?string $accessToken = null;

    public function sendToToken(string $token, string $title, string $body): void
    {
        $projectId = config('services.fcm.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];
        Http::withToken($this->getAccessToken())
            ->post($url, $payload);

            // dd(        Http::withToken($this->getAccessToken())
            // ->post($url, $payload));
            // die;
    }

    public function sendToTokens(array $tokens, string $title, string $body): void
    {
        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body);
        }
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }
        $credentialsPath = config('services.fcm.credentials');
        if (!$credentialsPath || !is_file($credentialsPath)) {
            throw new \RuntimeException('FCM credentials file not configured or missing.');
        }
        $key = json_decode(file_get_contents($credentialsPath), true);
        $jwt = $this->createJwt($key);
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        $data = $res->json();
        if (empty($data['access_token'])) {
            throw new \RuntimeException('FCM: failed to obtain access token.');
        }
        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }

    private function createJwt(array $key): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => $key['client_email'],
            'sub' => $key['client_email'],
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ];
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];
        $signature = '';
        $signInput = implode('.', $segments);
        $ok = openssl_sign($signInput, $signature, $key['private_key'], OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new \RuntimeException('FCM: JWT signing failed.');
        }
        $segments[] = $this->base64UrlEncode($signature);
        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
