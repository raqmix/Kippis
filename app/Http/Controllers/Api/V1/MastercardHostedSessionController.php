<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MastercardHostedSessionController extends Controller
{
    public function createSession(): JsonResponse
    {
        $merchantId = config('mastercard.merchant_id');
        $apiUsername = config('mastercard.api_username') ?: $merchantId;
        $apiPassword = config('mastercard.api_password');
        if (!$merchantId || !$apiPassword) {
            return apiError('PAYMENT_CONFIG_MISSING', 'payment_gateway_not_configured', 503);
        }

        $base = rtrim(config('mastercard.gateway'), '/');
        $version = config('mastercard.api_version');
        $url = "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/session";

        $customer = auth('api')->user();
        $gatewayOrderId = 'ver_' . ($customer ? $customer->id : 'guest') . '_' . time();
        $currency = config('mastercard.currency', 'EGP');
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        $payload = [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'order' => [
                'id' => $gatewayOrderId,
                'amount' => '100.00',
                'currency' => $currency
            ],
            'interaction' => [
                'operation' => 'NONE',
                'returnUrl' => "{$frontendUrl}/checkout?mpgs_return=1"
            ]
        ];

        $response = Http::withBasicAuth($apiUsername, $apiPassword)
            ->acceptJson()
            ->withBody(json_encode($payload), 'application/json')
            ->post($url);

        /** @var \Illuminate\Http\Client\Response $response */
        if (!$response->successful()) {
            Log::warning('Mastercard session failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
            return apiError(
                'SESSION_CREATE_FAILED',
                $response->json('error.message', 'payment_gateway_error'),
                $response->status()
            );
        }

        $session = $response->json('session');
        $sessionId = $session['id'] ?? null;
        if (!$sessionId) {
            return apiError('SESSION_CREATE_FAILED', 'invalid_gateway_response', 502);
        }

        $base = rtrim(config('mastercard.gateway'), '/');
        // For API v63+, MPGS uses a static un-versioned checkout.js URL
        $checkoutJsUrl = "{$base}/static/checkout/checkout.min.js";

        return apiSuccess([
            'session_id'      => $sessionId,
            'merchant_id'     => $merchantId,
            'checkout_js_url' => $checkoutJsUrl,
        ]);
    }
}
