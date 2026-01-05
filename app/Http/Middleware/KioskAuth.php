<?php

namespace App\Http\Middleware;

use App\Core\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KioskAuth
{
    /**
     * Handle an incoming request.
     *
     * Validates kiosk authentication using Store ID + API Key.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $storeId = $request->header('X-Store-ID');
        $apiKey = $request->header('X-Kiosk-API-Key');

        // Check if required headers are present
        if (!$storeId || !$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
                'message' => 'Missing required authentication headers. Provide X-Store-ID and X-Kiosk-API-Key.',
            ], 401);
        }

        // Find store by ID
        $store = Store::find($storeId);

        if (!$store) {
            return response()->json([
                'success' => false,
                'error' => 'STORE_NOT_FOUND',
                'message' => 'Store not found.',
            ], 404);
        }

        // Check if store is active
        if (!$store->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'STORE_INACTIVE',
                'message' => 'Store is not active.',
            ], 403);
        }

        // Check if store receives online orders
        if (!$store->receive_online_orders) {
            return response()->json([
                'success' => false,
                'error' => 'STORE_NOT_ACCEPTING_ORDERS',
                'message' => 'Store is not accepting online orders.',
            ], 403);
        }

        // Validate API key
        if (!$store->kiosk_api_key || $store->kiosk_api_key !== $apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_API_KEY',
                'message' => 'Invalid API key.',
            ], 401);
        }

        // Attach authenticated store to request for controllers to access
        $request->attributes->set('kiosk_store', $store);

        return $next($request);
    }
}

