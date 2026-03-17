<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class MastercardPaymentService
{
    private function makeClient(): Client
    {
        return new Client(['timeout' => 30]);
    }

    private function authHeaders(): array
    {
        $merchantId  = config('mastercard.merchant_id');
        $apiUsername = config('mastercard.api_username') ?: $merchantId;
        $apiPassword = config('mastercard.api_password');
        return [$merchantId, $apiUsername, $apiPassword];
    }

    private function txUrl(string $gatewayOrderId, string $transactionId): string
    {
        $base    = rtrim(config('mastercard.gateway'), '/');
        $version = config('mastercard.api_version');
        [$merchantId] = $this->authHeaders();
        return "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/order/{$gatewayOrderId}/transaction/{$transactionId}";
    }

    /**
     * Step 1 of 3DS: INITIATE_AUTHENTICATION.
     *
     * Creates the transaction on the gateway and determines whether 3DS is
     * required for this card / order.
     *
     * Correct payload: only apiOperation + order.currency + session.id.
     * Do NOT include order.amount, authentication.*, or sourceOfFunds — MPGS
     * rejects them as unexpected parameters at this stage.
     *
     * Returns:
     *   ['success' => true, 'authentication_required' => false] — 3DS not required, call PAY directly
     *   ['success' => true, 'authentication_required' => true]  — must call authenticatePayer() next
     *   ['success' => false, ...]
     */
    public function initiateAuthentication(
        string $gatewayOrderId,
        string $transactionId,
        string $currency,
        string $sessionId
    ): array {
        [, $apiUsername, $apiPassword] = $this->authHeaders();
        if (!$apiUsername || !$apiPassword) {
            return ['success' => false, 'error' => 'PAYMENT_CONFIG_MISSING', 'message' => 'payment_gateway_not_configured', 'status' => 503];
        }

        $url     = $this->txUrl($gatewayOrderId, $transactionId);
        $payload = [
            'apiOperation'   => 'INITIATE_AUTHENTICATION',
            'authentication' => [
                'channel' => 'PAYER_BROWSER',
                'purpose' => 'PAYMENT_TRANSACTION',
            ],
            'order'          => ['currency' => $currency],
            'session'        => ['id' => $sessionId],
        ];

        try {
            $response     = $this->makeClient()->put($url, [
                'auth'    => [$apiUsername, $apiPassword],
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'body'    => json_encode($payload),
            ]);
            $statusCode   = $response->getStatusCode();
            $body         = json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 502;
            $body       = $e->hasResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null;
            Log::error('Mastercard INITIATE_AUTHENTICATION failed', ['status' => $statusCode, 'body' => $body]);
            return [
                'success' => false, 'error' => 'INITIATE_AUTH_FAILED',
                'message' => $body['error']['explanation'] ?? $body['error']['message'] ?? $e->getMessage(),
                'status'  => $statusCode,
            ];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            Log::warning('Mastercard INITIATE_AUTHENTICATION failed', ['status' => $statusCode, 'body' => $body]);
            return [
                'success' => false, 'error' => 'INITIATE_AUTH_FAILED',
                'message' => $body['error']['explanation'] ?? $body['error']['message'] ?? 'payment_gateway_error',
                'status'  => $statusCode,
            ];
        }

        // PROCEED means 3DS is not required; anything else means we must call AUTHENTICATE_PAYER
        $recommendation      = $body['response']['gatewayRecommendation'] ?? '';
        $authenticationRequired = ($recommendation !== 'PROCEED');

        return ['success' => true, 'authentication_required' => $authenticationRequired];
    }

    /**
     * Step 2 of 3DS: AUTHENTICATE_PAYER.
     *
     * Must be called only after a successful INITIATE_AUTHENTICATION that returns
     * authentication_required = true. Uses the same transactionId.
     *
     * Returns:
     *   ['success' => true, 'requires_redirect' => false]  — frictionless / no challenge
     *   ['success' => true, 'requires_redirect' => true, 'redirect_html' => '<base64 HTML>']
     *   ['success' => false, ...]
     *
     * @param array $browserDetails Keys: colorDepth, language, screenHeight, screenWidth, timeZone, ipAddress
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
        [, $apiUsername, $apiPassword] = $this->authHeaders();
        if (!$apiUsername || !$apiPassword) {
            return ['success' => false, 'error' => 'PAYMENT_CONFIG_MISSING', 'message' => 'payment_gateway_not_configured', 'status' => 503];
        }

        $url = $this->txUrl($gatewayOrderId, $transactionId);

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
            ],
            'order'   => ['amount' => $amount, 'currency' => $currency],
            'session' => ['id' => $sessionId],
        ];

        if (!empty($browserDetails['ipAddress'])) {
            $payload['device']['ipAddress'] = $browserDetails['ipAddress'];
        }

        try {
            $response     = $this->makeClient()->put($url, [
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
     * Step 3: PAY — charge the session after authentication.
     *
     * The session must already contain card data (populated by the browser via
     * PaymentSession.updateSessionFromForm before calling this method).
     */
    public function pay(string $gatewayOrderId, string $transactionId, string $amount, string $currency, string $sessionId): array
    {
        [, $apiUsername, $apiPassword] = $this->authHeaders();

        if (!$apiUsername || !$apiPassword) {
            return ['success' => false, 'error' => 'PAYMENT_CONFIG_MISSING', 'message' => 'payment_gateway_not_configured', 'status' => 503];
        }

        $url = $this->txUrl($gatewayOrderId, $transactionId);

        $payload = [
            'apiOperation' => 'PAY',
            'order'        => ['amount' => $amount, 'currency' => $currency],
            'session'      => ['id' => $sessionId],
        ];

        try {
            $response     = $this->makeClient()->put($url, [
                'auth'    => [$apiUsername, $apiPassword],
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'body'    => json_encode($payload),
            ]);
            $statusCode   = $response->getStatusCode();
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                return ['success' => true, 'response' => $responseBody];
            }

            Log::warning('Mastercard Pay failed', ['status' => $statusCode, 'body' => $responseBody]);
            return [
                'success' => false,
                'error'   => 'PAY_FAILED',
                'message' => $responseBody['error']['explanation'] ?? $responseBody['error']['message'] ?? 'payment_gateway_error',
                'status'  => $statusCode,
            ];
        } catch (RequestException $e) {
            $statusCode   = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 502;
            $responseBody = $e->hasResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null;
            Log::warning('Mastercard Pay failed', ['status' => $statusCode, 'body' => $responseBody]);
            return [
                'success' => false,
                'error'   => 'PAY_FAILED',
                'message' => $responseBody['error']['explanation'] ?? $responseBody['error']['message'] ?? $e->getMessage(),
                'status'  => $statusCode,
            ];
        }
    }
}
