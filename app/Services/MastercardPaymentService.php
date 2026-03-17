<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class MastercardPaymentService
{
    /**
     * Run AUTHENTICATE_PAYER (3DS2) for a Hosted Session.
     *
     * Returns:
     *   ['success' => true, 'requires_redirect' => false]  — frictionless / no 3DS
     *   ['success' => true, 'requires_redirect' => true, 'redirect_html' => '<base64 HTML>']
     *   ['success' => false, 'error' => ..., 'message' => ..., 'status' => ...]
     *
     * @param array $browserDetails Keys: colorDepth, language, screenHeight, screenWidth, timeZone, userAgent
     */
    public function authenticatePayer(
        string $gatewayOrderId,
        string $transactionId,
        string $amount,
        string $currency,
        string $sessionId,
        string $redirectResponseUrl,
        array  $browserDetails = []
    ): array {
        $merchantId  = config('mastercard.merchant_id');
        $apiUsername = config('mastercard.api_username') ?: $merchantId;
        $apiPassword = config('mastercard.api_password');
        $base        = rtrim(config('mastercard.gateway'), '/');
        $version     = config('mastercard.api_version');
        $url         = "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/order/{$gatewayOrderId}/transaction/{$transactionId}";

        $payload = [
            'apiOperation'   => 'AUTHENTICATE_PAYER',
            'authentication' => [
                'redirectResponseUrl' => $redirectResponseUrl,
            ],
            'device' => [
                'browser'        => 'MOZILLA',
                'browserDetails' => [
                    '3DSecureChallengeWindowSize' => 'FULL_SCREEN',
                    'acceptHeaders'               => 'application/json',
                    'colorDepth'                  => (int) ($browserDetails['colorDepth'] ?? 24),
                    'javaEnabled'                 => false,
                    'language'                    => $browserDetails['language'] ?? 'en-US',
                    'screenHeight'                => (int) ($browserDetails['screenHeight'] ?? 768),
                    'screenWidth'                 => (int) ($browserDetails['screenWidth'] ?? 1366),
                    'timeZone'                    => (int) ($browserDetails['timeZone'] ?? 0),
                ],
                'ipAddress' => $browserDetails['ipAddress'] ?? null,
            ],
            'order'   => ['amount' => $amount, 'currency' => $currency],
            'session' => ['id' => $sessionId],
        ];

        // Remove null ipAddress to avoid sending it when missing
        if ($payload['device']['ipAddress'] === null) {
            unset($payload['device']['ipAddress']);
        }

        try {
            $client   = new Client(['timeout' => 30]);
            $response = $client->put($url, [
                'auth'    => [$apiUsername, $apiPassword],
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'body'    => json_encode($payload),
            ]);

            $statusCode   = $response->getStatusCode();
            $responseBody = json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $statusCode   = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 502;
            $responseBody = $e->hasResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null;
            Log::error('Mastercard AUTHENTICATE_PAYER failed', ['status' => $statusCode, 'body' => $responseBody]);
            return [
                'success' => false,
                'error'   => 'AUTH_PAYER_FAILED',
                'message' => $responseBody['error']['explanation'] ?? $responseBody['error']['message'] ?? $e->getMessage(),
                'status'  => $statusCode,
            ];
        }

        $authStatus  = $responseBody['transaction']['authenticationStatus'] ?? '';
        $redirectHtml = $responseBody['authentication']['redirect']['html'] ?? null;

        // 3DS challenge required — return the redirect HTML for the browser to submit
        if ($redirectHtml && in_array($authStatus, [
            'AUTHENTICATION_REDIRECT',
            'PAYER_AUTHENTICATION_IN_PROGRESS',
        ])) {
            return [
                'success'          => true,
                'requires_redirect' => true,
                'redirect_html'    => $redirectHtml,
            ];
        }

        // Frictionless / 3DS not supported — the session already has auth data, proceed to PAY
        if ($statusCode >= 200 && $statusCode < 300) {
            return ['success' => true, 'requires_redirect' => false];
        }

        Log::warning('Mastercard AUTHENTICATE_PAYER unexpected response', ['status' => $statusCode, 'body' => $responseBody]);
        return [
            'success' => false,
            'error'   => 'AUTH_PAYER_FAILED',
            'message' => $responseBody['error']['explanation'] ?? $responseBody['error']['message'] ?? 'payment_gateway_error',
            'status'  => $statusCode,
        ];
    }

    /**
     *
     * The session must already contain card data (populated by the browser via
     * PaymentSession.updateSessionFromForm before calling this method).
     */
    public function pay(string $gatewayOrderId, string $transactionId, string $amount, string $currency, string $sessionId): array
    {
        $merchantId = config('mastercard.merchant_id');
        $apiUsername = config('mastercard.api_username') ?: $merchantId;
        $apiPassword = config('mastercard.api_password');

        if (!$merchantId || !$apiPassword) {
            return ['success' => false, 'error' => 'PAYMENT_CONFIG_MISSING', 'message' => 'payment_gateway_not_configured', 'status' => 503];
        }

        $base = rtrim(config('mastercard.gateway'), '/');
        $version = config('mastercard.api_version');
        $url = "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/order/{$gatewayOrderId}/transaction/{$transactionId}";

        $payload = [
            'apiOperation' => 'PAY',
            'order' => [
                'amount' => $amount,
                'currency' => $currency,
            ],
            'session' => [
                'id' => $sessionId,
            ]
        ];

        try {
            $client = new Client(['timeout' => 30]);
            $response = $client->put($url, [
                'auth' => [$apiUsername, $apiPassword],
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'body' => json_encode($payload),
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                return ['success' => true, 'response' => $responseBody];
            }

            Log::warning('Mastercard Pay failed', ['status' => $statusCode, 'body' => $responseBody]);
            return [
                'success' => false,
                'error' => 'PAY_FAILED',
                'message' => $responseBody['error']['explanation'] ?? $responseBody['error']['message'] ?? 'payment_gateway_error',
                'status' => $statusCode,
            ];
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 502;
            $responseBody = $e->hasResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null;
            Log::warning('Mastercard Pay failed', ['status' => $statusCode, 'body' => $responseBody]);
            return [
                'success' => false,
                'error' => 'PAY_FAILED',
                'message' => $responseBody['error']['explanation'] ?? $responseBody['error']['message'] ?? $e->getMessage(),
                'status' => $statusCode,
            ];
        }
    }
}
