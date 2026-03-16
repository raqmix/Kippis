<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class MastercardPaymentService
{
    /**
     * Verify a payment completed through the Hosted Checkout Lightbox (PURCHASE operation).
     * Retrieves the MPGS order and confirms it has a successful captured transaction.
     */
    public function verifyPayment(string $gatewayOrderId, int $customerId): array
    {
        // Basic sanity check: order ID must belong to this customer
        if (!str_starts_with($gatewayOrderId, 'mpgs_' . $customerId . '_')) {
            return ['success' => false, 'error' => 'PAYMENT_INVALID', 'message' => 'invalid_gateway_order', 'status' => 422];
        }

        $merchantId = config('mastercard.merchant_id');
        $apiUsername = config('mastercard.api_username') ?: $merchantId;
        $apiPassword = config('mastercard.api_password');

        if (!$merchantId || !$apiPassword) {
            return ['success' => false, 'error' => 'PAYMENT_CONFIG_MISSING', 'message' => 'payment_gateway_not_configured', 'status' => 503];
        }

        $base = rtrim(config('mastercard.gateway'), '/');
        $version = config('mastercard.api_version');
        $url = "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/order/{$gatewayOrderId}";

        try {
            $client = new Client(['timeout' => 30]);
            $response = $client->get($url, [
                'auth' => [$apiUsername, $apiPassword],
                'headers' => ['Accept' => 'application/json'],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $status = $body['status'] ?? '';
            $result = $body['result'] ?? '';

            // A successful PURCHASE leaves the order in CAPTURED status
            if ($result === 'SUCCESS' || in_array($status, ['CAPTURED', 'AUTHORIZED'])) {
                return ['success' => true, 'response' => $body];
            }

            Log::warning('Mastercard payment not verified', ['order' => $gatewayOrderId, 'body' => $body]);
            return ['success' => false, 'error' => 'PAYMENT_NOT_VERIFIED', 'message' => 'payment_not_completed', 'status' => 402];
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 502;
            $responseBody = $e->hasResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null;
            Log::warning('Mastercard verifyPayment failed', ['status' => $statusCode, 'body' => $responseBody]);
            return [
                'success' => false,
                'error' => 'PAYMENT_VERIFY_FAILED',
                'message' => $responseBody['error']['explanation'] ?? $responseBody['error']['message'] ?? $e->getMessage(),
                'status' => $statusCode,
            ];
        }
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
