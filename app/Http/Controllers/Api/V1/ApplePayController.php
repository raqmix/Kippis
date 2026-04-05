<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CartRepository;
use App\Core\Repositories\OrderRepository;
use App\Core\Repositories\PaymentMethodRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @group Apple Pay APIs
 */
class ApplePayController extends Controller
{
    public function __construct(
        private OrderRepository $orderRepository,
        private PaymentMethodRepository $paymentMethodRepository,
        private CartRepository $cartRepository,
    ) {
    }

    /**
     * Validate Apple Pay merchant session.
     *
     * Called by the web client during `ApplePaySession.onvalidatemerchant`.
     * Proxies a request to Apple's servers using the merchant identity
     * certificate to obtain a merchant session object, which is then
     * returned to the frontend to complete validation.
     *
     * @bodyParam validation_url string required The URL provided by Apple in the onvalidatemerchant event. Example: https://apple-pay-gateway.apple.com/paymentservices/startSession
     *
     * @response 200 {"success": true, "data": {"merchant_session": {}}}
     * @response 503 {"success": false, "error": {"code": "APPLE_PAY_NOT_CONFIGURED"}}
     */
    public function merchantSession(Request $request): JsonResponse
    {
        $request->validate(['validation_url' => 'required|url']);

        $merchantId   = config('apple_pay.merchant_id');
        $certPath     = config('apple_pay.merchant_cert_path');
        $keyPath      = config('apple_pay.merchant_key_path');
        $domainName   = config('apple_pay.domain_name');
        $displayName  = config('apple_pay.display_name', 'Kippis');

        if (!$merchantId || !$certPath || !$keyPath) {
            return apiError('APPLE_PAY_NOT_CONFIGURED', 'apple_pay_not_configured', 503);
        }

        $validationUrl = $request->input('validation_url');

        // Ensure the validation URL is an Apple domain to prevent SSRF
        if (!str_ends_with(parse_url($validationUrl, PHP_URL_HOST), '.apple.com')) {
            return apiError('INVALID_VALIDATION_URL', 'invalid_apple_pay_validation_url', 422);
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withOptions([
                'cert'    => $certPath,
                'ssl_key' => $keyPath,
            ])->post($validationUrl, [
                'merchantIdentifier' => $merchantId,
                'domainName'         => $domainName,
                'displayName'        => $displayName,
            ]);

            if (!$response->successful()) {
                Log::warning('Apple Pay merchant session failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return apiError('MERCHANT_SESSION_FAILED', 'apple_pay_merchant_session_failed', 502);
            }

            return apiSuccess(['merchant_session' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('Apple Pay merchant session error', ['message' => $e->getMessage()]);
            return apiError('MERCHANT_SESSION_ERROR', 'apple_pay_gateway_unreachable', 502);
        }
    }

    /**
     * Process an Apple Pay payment token.
     *
     * Receives the encrypted Apple Pay payment token from the client
     * (mobile or web), submits it to the payment gateway (MPGS Wallet API),
     * and creates the order upon success.
     *
     * @bodyParam payment_token object required The Apple Pay payment token from PKPaymentToken/ApplePayPayment. Example: {}
     * @bodyParam store_id int required The ID of the pickup store. Example: 1
     *
     * @response 200 {"success": true, "data": {"order_id": 42, "order_number": "ORD-001"}}
     * @response 422 {"success": false, "error": {"code": "VALIDATION_ERROR"}}
     */
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'payment_token' => 'required|array',
            'store_id'      => 'required|integer|exists:stores,id',
        ]);

        $merchantId   = config('mastercard.merchant_id');
        $apiUsername  = config('mastercard.api_username') ?: $merchantId;
        $apiPassword  = config('mastercard.api_password');

        if (!$merchantId || !$apiPassword) {
            return apiError('PAYMENT_CONFIG_MISSING', 'payment_gateway_not_configured', 503);
        }

        $paymentMethod = $this->paymentMethodRepository->findByCode('apple_pay');
        if (!$paymentMethod) {
            return apiError('PAYMENT_METHOD_NOT_FOUND', 'apple_pay_payment_method_not_found', 404);
        }

        $base       = rtrim(config('mastercard.gateway'), '/');
        $version    = config('mastercard.api_version');
        $orderId    = 'APPLEPAY-' . strtoupper(uniqid());
        $currency   = config('mastercard.currency', 'SAR');

        $cart = $this->cartRepository->findActiveCart($request->user()->id, true);
        if (!$cart || $cart->items->isEmpty()) {
            return apiError('CART_EMPTY', 'cart_is_empty', 422);
        }

        $this->cartRepository->recalculate($cart);
        $cart->refresh();

        $amount = number_format((float) $cart->total, 2, '.', '');

        $walletUrl = "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/order/{$orderId}/transaction/pay";

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($apiUsername, $apiPassword)->asJson()->put($walletUrl, [
                'apiOperation' => 'PAY',
                'order'        => [
                    'amount'   => $amount,
                    'currency' => $currency,
                ],
                'sourceOfFunds' => [
                    'type'   => 'APPLE_PAY',
                    'provided' => [
                        'applePayToken' => $request->input('payment_token'),
                    ],
                ],
                'transaction' => ['reference' => $orderId],
            ]);

            if (!$response->successful() || !in_array($response->json('result'), ['SUCCESS', 'APPROVED'])) {
                Log::warning('Apple Pay MPGS payment failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return apiError('PAYMENT_FAILED', $response->json('error.explanation') ?? 'apple_pay_payment_failed', 402);
            }
        } catch (\Exception $e) {
            Log::error('Apple Pay MPGS payment error', ['message' => $e->getMessage()]);
            return apiError('PAYMENT_ERROR', 'payment_gateway_unreachable', 502);
        }

        $order = $this->orderRepository->createFromCart(
            $cart,
            $paymentMethod->id,
            (int) $request->input('store_id')
        );

        return apiSuccess([
            'order_id'    => $order->id,
            'pickup_code' => $order->pickup_code,
            'total'       => (float) $order->total,
        ]);
    }
}
