<?php

namespace Tests\Feature;

use App\Integrations\Foodics\Exceptions\FoodicsRateLimitException;
use App\Integrations\Foodics\Services\FoodicsAuthService;
use App\Integrations\Foodics\Services\FoodicsClient;
use App\Integrations\Foodics\Services\FoodicsRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Guards the fix for the Foodics 429 flood (29K rejected requests/week).
 *
 * The defect these cover: the client answered a 429 by sleeping a fixed
 * 2/4/6s and retrying, inside a limit that resets on a 60s window. Every
 * retry was therefore certain to 429 too, so each rejection cost four
 * requests instead of one — the retry amplified the very problem it was
 * meant to absorb.
 */
class FoodicsRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['foodics.mode' => 'live']);

        // Stub the token lookup so these tests exercise the HTTP/retry path
        // without needing a migrated foodics_tokens table.
        $auth = $this->createMock(FoodicsAuthService::class);
        $auth->method('getAccessToken')->willReturn('test-token');
        $this->app->instance(FoodicsAuthService::class, $auth);
    }

    private function countSent(): int
    {
        $sent = 0;
        Http::recorded(function () use (&$sent) {
            $sent++;
            return false;
        });

        return $sent;
    }

    public function test_long_retry_after_is_not_retried_and_does_not_block(): void
    {
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429, ['retry-after' => '45'])]);

        $start = microtime(true);
        $this->expectException(FoodicsRateLimitException::class);

        try {
            app(FoodicsClient::class)->get('v5/categories');
        } finally {
            $elapsed = microtime(true) - $start;

            // One request, not four: a 45s wait exceeds what we will sit
            // through, so we throw immediately and let the caller's own
            // backoff handle it.
            $this->assertSame(1, $this->countSent(), 'should send exactly one request');
            $this->assertLessThan(5, $elapsed, 'must not sleep out a long Retry-After');
        }
    }

    public function test_short_retry_after_is_honoured_not_the_old_fixed_delay(): void
    {
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429, ['retry-after' => '1'])]);

        $start = microtime(true);
        try {
            app(FoodicsClient::class)->get('v5/categories');
        } catch (FoodicsRateLimitException $e) {
            // expected once retries are exhausted
        }
        $elapsed = microtime(true) - $start;

        // 1 initial + 3 retries, each waiting the 1s Foodics asked for.
        $this->assertSame(4, $this->countSent());
        $this->assertGreaterThanOrEqual(2.5, $elapsed, 'should have honoured Retry-After between attempts');
    }

    public function test_a_429_stops_further_spending_in_the_same_window(): void
    {
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429, ['retry-after' => '45'])]);

        try {
            app(FoodicsClient::class)->get('v5/categories');
        } catch (FoodicsRateLimitException $e) {
            // expected
        }

        // The 429 handler dumps the window, so the limiter now reports no
        // budget left even though we only spent one request of 60.
        $limiter = app(FoodicsRateLimiter::class);
        $tryConsume = (new \ReflectionClass($limiter))->getMethod('tryConsume');
        $tryConsume->setAccessible(true);

        $this->assertFalse($tryConsume->invoke($limiter), 'window should be exhausted after a 429');
    }

    public function test_budget_caps_requests_per_window(): void
    {
        $limiter = app(FoodicsRateLimiter::class);
        $tryConsume = (new \ReflectionClass($limiter))->getMethod('tryConsume');
        $tryConsume->setAccessible(true);

        $allowed = 0;
        for ($i = 0; $i < 80; $i++) {
            if ($tryConsume->invoke($limiter)) {
                $allowed++;
            }
        }

        $this->assertSame(60, $allowed, 'should allow exactly the 60-request budget');
    }

    public function test_successful_requests_still_pass_through(): void
    {
        Http::fake(['*' => Http::response(['data' => [['id' => 'abc']]], 200)]);

        $response = app(FoodicsClient::class)->get('v5/categories');

        $this->assertTrue($response->ok);
        $this->assertSame(1, $this->countSent());
    }
}
