<?php

namespace Tests\Feature;

use App\Services\MastercardPaymentService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * Gateway responses follow the MPGS 3DS guide: INITIATE_AUTHENTICATION
 * reports AUTHENTICATION_AVAILABLE + PROCEED when 3DS can run; a challenge
 * comes back from AUTHENTICATE_PAYER as AUTHENTICATION_PENDING with redirect
 * HTML; frictionless as AUTHENTICATION_SUCCESSFUL.
 */
class Mastercard3dsFlowTest extends TestCase
{
    private function service(array $responses): MastercardPaymentService
    {
        config([
            'mastercard.merchant_id' => 'TESTMERCHANT',
            'mastercard.api_password' => 'x',
            'mastercard.gateway' => 'https://gateway.test',
            'mastercard.api_version' => '100',
        ]);
        $stack = HandlerStack::create(new MockHandler(array_map(
            fn (array $body) => new Response(200, ['Content-Type' => 'application/json'], json_encode($body)),
            $responses
        )));

        return new class($stack) extends MastercardPaymentService {
            public function __construct(private HandlerStack $stack) {}
            protected function makeClient(): Client
            {
                return new Client(['handler' => $this->stack]);
            }
        };
    }

    public function test_proceed_with_available_means_authenticate_payer_is_required(): void
    {
        $r = $this->service([[
            'response' => ['gatewayRecommendation' => 'PROCEED'],
            'transaction' => ['authenticationStatus' => 'AUTHENTICATION_AVAILABLE'],
        ]])->initiateAuthentication('ord_1', 'auth_1', 'EGP', 'SESSION1');

        $this->assertTrue($r['success']);
        $this->assertTrue($r['authentication_required'], 'PROCEED + AVAILABLE must lead to AUTHENTICATE_PAYER, not straight to PAY');
    }

    public function test_not_supported_card_pays_without_authentication(): void
    {
        $r = $this->service([[
            'response' => ['gatewayRecommendation' => 'PROCEED'],
            'transaction' => ['authenticationStatus' => 'AUTHENTICATION_NOT_SUPPORTED'],
        ]])->initiateAuthentication('ord_1', 'auth_1', 'EGP', 'SESSION1');

        $this->assertTrue($r['success']);
        $this->assertFalse($r['authentication_required']);
    }

    public function test_resubmit_recommendation_is_rejected(): void
    {
        $r = $this->service([[
            'response' => ['gatewayRecommendation' => 'RESUBMIT_WITH_ALTERNATIVE_PAYMENT_DETAILS'],
            'transaction' => ['authenticationStatus' => 'AUTHENTICATION_UNAVAILABLE'],
        ]])->initiateAuthentication('ord_1', 'auth_1', 'EGP', 'SESSION1');

        $this->assertFalse($r['success']);
        $this->assertSame('CARD_NOT_SUPPORTED', $r['error']);
    }

    public function test_pending_with_html_is_a_challenge(): void
    {
        $r = $this->service([[
            'response' => ['gatewayRecommendation' => 'PROCEED'],
            'transaction' => ['authenticationStatus' => 'AUTHENTICATION_PENDING'],
            'authentication' => ['redirect' => ['html' => '<div id="threedsChallengeRedirect">…</div>']],
        ]])->authenticatePayer('ord_1', 'auth_1', '10.00', 'EGP', 'SESSION1', 'https://api.test/3ds-return/ord_1');

        $this->assertTrue($r['success']);
        $this->assertTrue($r['requires_redirect']);
        $this->assertStringContainsString('threedsChallengeRedirect', $r['redirect_html']);
    }

    public function test_successful_is_frictionless(): void
    {
        $r = $this->service([[
            'response' => ['gatewayRecommendation' => 'PROCEED'],
            'transaction' => ['authenticationStatus' => 'AUTHENTICATION_SUCCESSFUL'],
        ]])->authenticatePayer('ord_1', 'auth_1', '10.00', 'EGP', 'SESSION1', 'https://api.test/3ds-return/ord_1');

        $this->assertTrue($r['success']);
        $this->assertFalse($r['requires_redirect']);
    }

    public function test_failed_authentication_is_not_charged(): void
    {
        $r = $this->service([[
            'response' => ['gatewayRecommendation' => 'DO_NOT_PROCEED'],
            'transaction' => ['authenticationStatus' => 'AUTHENTICATION_FAILED'],
        ]])->authenticatePayer('ord_1', 'auth_1', '10.00', 'EGP', 'SESSION1', 'https://api.test/3ds-return/ord_1');

        $this->assertFalse($r['success']);
        $this->assertSame('CARD_AUTH_DECLINED', $r['error']);
    }
}
