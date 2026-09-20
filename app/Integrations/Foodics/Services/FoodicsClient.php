<?php

namespace App\Integrations\Foodics\Services;

use App\Integrations\Foodics\DTOs\FoodicsErrorDTO;
use App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO;
use App\Integrations\Foodics\DTOs\FoodicsResponseDTO;
use App\Integrations\Foodics\Exceptions\FoodicsConnectionException;
use App\Integrations\Foodics\Exceptions\FoodicsException;
use App\Integrations\Foodics\Exceptions\FoodicsForbiddenException;
use App\Integrations\Foodics\Exceptions\FoodicsMaintenanceException;
use App\Integrations\Foodics\Exceptions\FoodicsNotFoundException;
use App\Integrations\Foodics\Exceptions\FoodicsRateLimitException;
use App\Integrations\Foodics\Exceptions\FoodicsServerErrorException;
use App\Integrations\Foodics\Exceptions\FoodicsTimeoutException;
use App\Integrations\Foodics\Exceptions\FoodicsUnauthorizedException;
use App\Integrations\Foodics\Exceptions\FoodicsValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FoodicsClient
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 2;

    /**
     * Longest Retry-After we will sit and wait for. Beyond this we give up
     * and throw, so a rate-limit episode cannot pin a queue worker (or an
     * HTTP request, in the kiosk's synchronous push path) for minutes.
     * The caller's own retry policy is the right place to wait that long.
     */
    private const MAX_RETRY_AFTER_WAIT = 20;

    public function __construct(
        private FoodicsAuthService $authService,
        private FoodicsRateLimiter $rateLimiter,
    ) {
    }

    /**
     * Make GET request to Foodics API.
     */
    public function get(string $endpoint, ?FoodicsQueryParamsDTO $queryParams = null, ?string $mode = null, int $retryCount = 0): FoodicsResponseDTO
    {
        return $this->request('GET', $endpoint, null, $queryParams, $mode, $retryCount);
    }

    /**
     * Make POST request to Foodics API with a JSON body. Same retry/exception
     * semantics as get(): 429 and 503 trigger exponential backoff retries.
     */
    public function post(string $endpoint, array $body, ?string $mode = null, int $retryCount = 0): FoodicsResponseDTO
    {
        return $this->request('POST', $endpoint, $body, null, $mode, $retryCount);
    }

    /**
     * Internal dispatcher used by every public verb. Centralizes auth, URL
     * building, status-code handling, retry, exception mapping, and logging.
     */
    private function request(
        string $method,
        string $endpoint,
        ?array $body = null,
        ?FoodicsQueryParamsDTO $queryParams = null,
        ?string $mode = null,
        int $retryCount = 0,
    ): FoodicsResponseDTO {
        try {
            $mode = $mode ?? config('foodics.mode', 'live');
            $token = $this->authService->getAccessToken($mode);
            $baseUrls = config('foodics.base_urls', []);
            $baseUrl = $baseUrls[$mode] ?? config('foodics.base_url') ?? 'https://api.foodics.com';
            $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

            $query = $queryParams ? $queryParams->toQuery() : [];

            Log::info('Foodics API Request', [
                'method' => $method,
                'url' => $url,
                'query' => $query,
                'has_body' => $body !== null,
            ]);

            $http = Http::timeout(config('foodics.timeout', 30))
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ]);

            // Spend from the shared per-minute budget before going out. This
            // is what actually keeps us under Foodics' limit; the 429 branch
            // below is the fallback for when our count and theirs disagree.
            $this->rateLimiter->acquire($method . ' ' . $endpoint);

            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $query),
                'POST' => $http->post($url, $body ?? []),
                default => throw new FoodicsServerErrorException("Unsupported HTTP method: {$method}"),
            };

            $statusCode = $response->status();

            Log::info('Foodics API Response', [
                'method' => $method,
                'status' => $statusCode,
                'url' => $url,
            ]);

            if ($statusCode === 401) {
                throw new FoodicsUnauthorizedException("Unauthorized. Please check your token in Foodics Test page for {$mode} mode.");
            }

            if ($statusCode === 403) {
                throw new FoodicsForbiddenException();
            }

            if ($statusCode === 404) {
                throw new FoodicsNotFoundException();
            }

            if ($statusCode === 422) {
                $errorData = $response->json();
                Log::warning('Foodics API 422 validation body', [
                    'method' => $method,
                    'url' => $url,
                    'body' => $errorData,
                    'request_payload' => $body,
                ]);
                throw new FoodicsValidationException(
                    $errorData['message'] ?? 'Validation error',
                    $errorData['errors'] ?? null
                );
            }

            if ($statusCode === 429) {
                // Our local budget is evidently out of step with Foodics'.
                // Stop spending for the rest of this minute regardless of
                // what the counter says.
                $this->rateLimiter->exhaustCurrentWindow();

                $maxRetries = config('foodics.retry.max_attempts', self::MAX_RETRIES);

                // Foodics tells us exactly how long to wait. Honour it: the
                // limit resets on a 60s window, so the old fixed 2/4/6s
                // backoff retried inside the very window that was already
                // exhausted and was guaranteed to 429 again — turning each
                // rejection into four.
                $retryAfter = (int) ($response->header('retry-after') ?: 0);

                Log::warning('FOODICS_RATE_LIMITED', [
                    'method' => $method,
                    'url' => $url,
                    'retry_after' => $retryAfter,
                    'attempt' => $retryCount + 1,
                    'rate_limit_remaining' => $response->header('x-ratelimit-remaining'),
                ]);

                $waitFor = $retryAfter > 0
                    ? $retryAfter
                    : config('foodics.retry.delay_seconds', self::RETRY_DELAY) * ($retryCount + 1);

                if ($retryCount < $maxRetries && $waitFor <= self::MAX_RETRY_AFTER_WAIT) {
                    sleep($waitFor);
                    return $this->request($method, $endpoint, $body, $queryParams, $mode, $retryCount + 1);
                }

                // Too long to wait here, or out of attempts. Throw and let
                // the caller decide — a queued job will retry on its own
                // backoff, which is cheaper than blocking a worker.
                throw new FoodicsRateLimitException();
            }

            if ($statusCode === 500) {
                throw new FoodicsServerErrorException();
            }

            if ($statusCode === 503) {
                $maxRetries = config('foodics.retry.max_attempts', self::MAX_RETRIES);
                $retryDelay = config('foodics.retry.delay_seconds', self::RETRY_DELAY);

                if ($retryCount < $maxRetries) {
                    sleep($retryDelay * ($retryCount + 1));
                    return $this->request($method, $endpoint, $body, $queryParams, $mode, $retryCount + 1);
                }
                throw new FoodicsMaintenanceException();
            }

            if ($response->successful()) {
                $responseData = $response->json();

                $paginationData = null;
                if (isset($responseData['links']) && isset($responseData['meta'])) {
                    $paginationData = [
                        'links' => $responseData['links'],
                        'meta' => $responseData['meta'],
                    ];
                }

                return FoodicsResponseDTO::success($responseData, $statusCode, $paginationData);
            }

            $errorData = $response->json();
            $error = FoodicsErrorDTO::fromArray($errorData['error'] ?? ['message' => 'Unknown error']);

            return FoodicsResponseDTO::error($error, $statusCode);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Foodics API Connection Error', [
                'method' => $method,
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            throw new FoodicsConnectionException('Connection failed: ' . $e->getMessage());
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->getCode() === 0 || str_contains($e->getMessage(), 'timeout')) {
                throw new FoodicsTimeoutException('Request timeout: ' . $e->getMessage());
            }
            throw new FoodicsConnectionException('Connection error: ' . $e->getMessage());
        } catch (FoodicsException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Foodics API Unexpected Error', [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'endpoint' => $endpoint,
            ]);

            throw new FoodicsServerErrorException('Unexpected error: ' . $e->getMessage());
        }
    }
}
