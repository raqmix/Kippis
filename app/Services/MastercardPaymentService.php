<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MastercardPaymentService
{
    /**
     * Verify a Hosted Checkout payment using MPGS's successIndicator mechanism.
     *
     * MPGS returns a `successIndicator` in the INITIATE_CHECKOUT response (stored in cache).
     * After the Lightbox PURCHASE completes, the JS complete(resultIndicator) callback echoes
     * that same value only for genuine successful payments. We compare them here server-side.
     */
    public function verifyPayment(string $gatewayOrderId, string $resultIndicator, int $customerId): array
    {
        // Ownership check: order ID must belong to this customer
        if (!str_starts_with($gatewayOrderId, 'mpgs_' . $customerId . '_')) {
            return ['success' => false, 'error' => 'PAYMENT_INVALID', 'message' => 'invalid_gateway_order', 'status' => 422];
        }

        $successIndicator = Cache::get("mpgs_success_{$gatewayOrderId}");

        if (!$successIndicator) {
            Log::warning('Mastercard: successIndicator not found in cache', ['order' => $gatewayOrderId]);
            return ['success' => false, 'error' => 'PAYMENT_SESSION_EXPIRED', 'message' => 'payment_session_expired', 'status' => 422];
        }

        if (!hash_equals($successIndicator, $resultIndicator)) {
            Log::warning('Mastercard: resultIndicator mismatch', ['order' => $gatewayOrderId]);
            return ['success' => false, 'error' => 'PAYMENT_NOT_VERIFIED', 'message' => 'payment_not_completed', 'status' => 402];
        }

        // Indicator matched — payment genuinely completed. Consume the cache entry.
        Cache::forget("mpgs_success_{$gatewayOrderId}");

        return ['success' => true];
    }

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
