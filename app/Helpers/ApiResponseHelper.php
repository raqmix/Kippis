<?php

/**
 * API Response Helper
 *
 * Provides unified response format for all API endpoints.
 */

use Illuminate\Http\JsonResponse;

if (!function_exists('apiSuccess')) {
    /**
     * Return a successful API response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @return JsonResponse
     */
    function apiSuccess($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }
}

if (!function_exists('apiError')) {
    /**
     * Return an error API response.
     *
     * @param string $code
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    function apiError(string $code, string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $statusCode);
    }
}
