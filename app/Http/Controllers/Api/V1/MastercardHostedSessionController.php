<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CartRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MastercardHostedSessionController extends Controller
{
    public function __construct(private CartRepository $cartRepository) {}

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

        $cart = $this->cartRepository->findActiveCart($customer->id);
        if (!$cart || $cart->items->isEmpty()) {
            return apiError('CART_EMPTY', 'cart_empty', 400);
        }
        $this->cartRepository->recalculate($cart);
        $cart->refresh();

        $amount = number_format((float) $cart->total, 2, '.', '');
        $gatewayOrderId = 'mpgs_' . $customer->id . '_' . time();
        $currency = config('mastercard.currency', 'EGP');
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        $payload = [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'order' => [
                'id' => $gatewayOrderId,
                'amount' => $amount,
                'currency' => $currency
            ],
            'interaction' => [
                'operation' => 'PURCHASE',
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
        $successIndicator = $response->json('successIndicator');
        if (!$sessionId || !$successIndicator) {
            return apiError('SESSION_CREATE_FAILED', 'invalid_gateway_response', 502);
        }

        // Store the success indicator server-side; compared against the resultIndicator
        // returned by the Lightbox complete() callback to verify genuine payment.
        Cache::put("mpgs_success_{$gatewayOrderId}", $successIndicator, now()->addMinutes(30));

        $base = rtrim(config('mastercard.gateway'), '/');
        // For API v63+, MPGS uses a static un-versioned checkout.js URL
        $checkoutJsUrl = "{$base}/static/checkout/checkout.min.js";

        return apiSuccess([
            'session_id'       => $sessionId,
            'gateway_order_id' => $gatewayOrderId,
            'merchant_id'      => $merchantId,
            'checkout_js_url'  => $checkoutJsUrl,
        ]);
    }
}
