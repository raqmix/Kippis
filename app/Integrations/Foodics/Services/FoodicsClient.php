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

    public function __construct(
        private FoodicsAuthService $authService
    ) {
    }

    /**
     * Make GET request to Foodics API.
     *
     * @param string $endpoint
     * @param FoodicsQueryParamsDTO|null $queryParams
     * @param int $retryCount
     * @return FoodicsResponseDTO
     * @throws FoodicsException
     */
    public function get(string $endpoint, ?FoodicsQueryParamsDTO $queryParams = null, int $retryCount = 0): FoodicsResponseDTO
    {
        try {
            $token = $this->authService->getAccessToken();
            $baseUrl = config('foodics.base_url', 'https://api.foodics.com');
            $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

            $query = $queryParams ? $queryParams->toQuery() : [];

            Log::info('Foodics API Request', [
                'method' => 'GET',
                'url' => $url,
                'query' => $query,
            ]);

            $response = Http::timeout(config('foodics.timeout', 30))
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get($url, $query);

            $statusCode = $response->status();

            Log::info('Foodics API Response', [
                'status' => $statusCode,
                'url' => $url,
            ]);

            // Handle specific status codes
            if ($statusCode === 401) {
                if ($retryCount === 0) {
                    $this->authService->refreshToken();
                    return $this->get($endpoint, $queryParams, $retryCount + 1);
                }
                throw new FoodicsUnauthorizedException();
            }

            if ($statusCode === 403) {
                throw new FoodicsForbiddenException();
            }

            if ($statusCode === 404) {
                throw new FoodicsNotFoundException();
            }

            if ($statusCode === 422) {
                $errorData = $response->json();
                throw new FoodicsValidationException(
                    $errorData['message'] ?? 'Validation error',
                    $errorData['errors'] ?? null
                );
            }

            if ($statusCode === 429) {
                $maxRetries = config('foodics.retry.max_attempts', self::MAX_RETRIES);
                $retryDelay = config('foodics.retry.delay_seconds', self::RETRY_DELAY);
                
                if ($retryCount < $maxRetries) {
                    sleep($retryDelay * ($retryCount + 1));
                    return $this->get($endpoint, $queryParams, $retryCount + 1);
                }
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
                    return $this->get($endpoint, $queryParams, $retryCount + 1);
                }
                throw new FoodicsMaintenanceException();
            }

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Extract pagination if present
                $paginationData = null;
                if (isset($responseData['links']) && isset($responseData['meta'])) {
                    $paginationData = [
                        'links' => $responseData['links'],
                        'meta' => $responseData['meta'],
                    ];
                }
                
                return FoodicsResponseDTO::success(
                    $responseData,
                    $statusCode,
                    $paginationData
                );
            }

            // Unknown error
            $errorData = $response->json();
            $error = FoodicsErrorDTO::fromArray($errorData['error'] ?? ['message' => 'Unknown error']);
            
            return FoodicsResponseDTO::error($error, $statusCode);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Foodics API Connection Error', [
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'endpoint' => $endpoint,
            ]);

            throw new FoodicsServerErrorException('Unexpected error: ' . $e->getMessage());
        }
    }
}

