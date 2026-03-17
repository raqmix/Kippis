<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MastercardHostedSessionController extends Controller
{
    /**
     * Create an MPGS Hosted Session.
     *
     * The session ID is returned to the frontend where session.js attaches
     * secure iFrame card fields via PaymentSession.configure(). After the
     * payer fills in their card details and calls updateSessionFromForm(),
     * the session holds the card data and the backend issues a PAY request.
     */
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

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($apiUsername, $apiPassword)
                ->asJson()
                ->post($url, ['session' => ['authenticationLimit' => 25]]);

            if (!$response->successful()) {
                Log::warning('Mastercard session failed', [
                    'status' => $response->status(),
                    'body'   => $response->json() ?? $response->body(),
                ]);
                // Always return 502 — never forward MPGS 401/403 to the browser
                // as that triggers the frontend auth interceptor and logs the user out.
                return apiError(
                    'SESSION_CREATE_FAILED',
                    $response->json('error.explanation') ?? $response->json('error.message') ?? 'payment_gateway_error',
                    502
                );
            }

            $sessionId = $response->json('session.id');
            if (!$sessionId) {
                return apiError('SESSION_CREATE_FAILED', 'invalid_gateway_response', 502);
            }
        } catch (ConnectionException $e) {
            Log::error('Mastercard session connection failed', ['message' => $e->getMessage()]);
            return apiError('SESSION_CREATE_FAILED', 'payment_gateway_unreachable', 502);
        }

        // session.js URL is versioned and merchant-specific (Hosted Session, not Hosted Checkout)
        $sessionJsUrl = "{$base}/form/version/{$version}/merchant/{$merchantId}/session.js";

        return apiSuccess([
            'session_id'     => $sessionId,
            'merchant_id'    => $merchantId,
            'session_js_url' => $sessionJsUrl,
        ]);
    }
}
