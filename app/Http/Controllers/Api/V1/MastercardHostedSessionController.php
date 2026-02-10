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

        $response = Http::withBasicAuth($apiUsername, $apiPassword)
            ->acceptJson()
            ->withBody('{}', 'application/json')
            ->post($url);

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

        return apiSuccess([
            'session_id' => $sessionId,
        ]);
    }
}
