<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Integrations\Foodics\Exceptions\FoodicsException;
use App\Modules\Stores\Services\FoodicsBranchesSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FoodicsSyncController extends Controller
{
    public function __construct(
        private FoodicsBranchesSyncService $syncService
    ) {
    }

    /**
     * Sync branches from Foodics.
     *
     * @return JsonResponse
     */
    public function syncBranches(): JsonResponse
    {
        try {
            $stats = $this->syncService->syncAllBranches();

            return response()->json([
                'success' => true,
                'message' => 'Branches synced successfully.',
                'data' => $stats,
            ]);
        } catch (FoodicsException $e) {
            Log::error('Foodics sync failed', [
                'error' => $e->getMessage(),
                'code' => $e->getErrorCode(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Foodics sync unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SYNC_ERROR',
                    'message' => 'Failed to sync branches. Please try again later.',
                ],
            ], 500);
        }
    }
}

