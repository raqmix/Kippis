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
     * @param string|null $message Translation key or message
     * @param int $statusCode
     * @return JsonResponse
     */
    function apiSuccess($data = null, ?string $message = null, int $statusCode = 200, ?array $meta = null): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            // Try to translate the message, fallback to original if translation not found
            $response['message'] = __("api.{$message}", [], app()->getLocale()) !== "api.{$message}"
                ? __("api.{$message}", [], app()->getLocale())
                : $message;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }
}

if (!function_exists('apiError')) {
    /**
     * Return an error API response.
     *
     * @param string $code
     * @param string $message Translation key or message
     * @param int $statusCode
     * @return JsonResponse
     */
    function apiError(string $code, string $message, int $statusCode = 400): JsonResponse
    {
        // Try to translate the message, fallback to original if translation not found
        $translatedMessage = __("api.{$message}", [], app()->getLocale()) !== "api.{$message}"
            ? __("api.{$message}", [], app()->getLocale())
            : $message;

        if ($statusCode < 100) {
            $statusCode = 400;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $translatedMessage,
            ],
        ], $statusCode);
    }
}
