<?php

namespace App\Modules\Stores\Services;

use App\Core\Models\Store;
use App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO;
use App\Integrations\Foodics\Services\FoodicsClient;
use Illuminate\Support\Facades\Log;

class FoodicsBranchesSyncService
{
    public function __construct(
        private FoodicsClient $client
    ) {
    }

    /**
     * Sync all branches from Foodics.
     *
     * @return array
     */
    public function syncAllBranches(): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            try {
                $queryParams = new FoodicsQueryParamsDTO(
                    page: $page,
                    include: [],
                    filters: [],
                    sort: 'created_at'
                );

                $response = $this->client->get('v5/branches', $queryParams);

                if (!$response->ok || !isset($response->data['data'])) {
                    Log::error('Foodics branches sync failed', [
                        'response' => $response->data,
                    ]);
                    $stats['errors']++;
                    break;
                }

                $branches = $response->data['data'] ?? [];
                
                if (empty($branches)) {
                    break;
                }

                foreach ($branches as $branchData) {
                    try {
                        $isNew = $this->isNewBranch($branchData['id'] ?? '');
                        $this->syncBranch($branchData);
                        
                        if ($isNew) {
                            $stats['created']++;
                        } else {
                            $stats['updated']++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to sync branch', [
                            'branch_id' => $branchData['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                        $stats['skipped']++;
                    }
                }

                // Check if there's a next page
                if ($response->pagination && $response->pagination->hasNextPage()) {
                    $page = $response->pagination->meta->current_page + 1;
                } else {
                    $hasMore = false;
                }
            } catch (\Exception $e) {
                Log::error('Foodics branches sync page error', [
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
                break;
            }
        }

        return $stats;
    }

    /**
     * Sync a single branch.
     *
     * @param array $branchData
     * @return Store
     */
    private function syncBranch(array $branchData): Store
    {
        // Ignore deleted branches
        if (isset($branchData['deleted_at']) && $branchData['deleted_at']) {
            throw new \Exception('Branch is deleted in Foodics');
        }

        // Ignore branches that don't receive online orders
        if (isset($branchData['receive_online_orders']) && !$branchData['receive_online_orders']) {
            throw new \Exception('Branch does not receive online orders');
        }

        $foodicsBranchId = $branchData['id'] ?? null;
        
        if (!$foodicsBranchId) {
            throw new \Exception('Branch ID is missing');
        }

        $storeData = [
            'name' => $branchData['name'] ?? '',
            'name_localized' => $branchData['name_localized'] ?? null,
            'address' => $branchData['address'] ?? null,
            'latitude' => isset($branchData['latitude']) ? (float) $branchData['latitude'] : null,
            'longitude' => isset($branchData['longitude']) ? (float) $branchData['longitude'] : null,
            'open_time' => $branchData['open_time'] ?? null,
            'close_time' => $branchData['close_time'] ?? null,
            'is_active' => $branchData['is_active'] ?? true,
            'receive_online_orders' => $branchData['receive_online_orders'] ?? true,
            'foodics_branch_id' => $foodicsBranchId,
            'synced_from_foodics_at' => now(),
        ];

        return Store::updateOrCreate(
            ['foodics_branch_id' => $foodicsBranchId],
            $storeData
        );
    }

    /**
     * Check if branch is new (not yet synced).
     *
     * @param string $foodicsBranchId
     * @return bool
     */
    private function isNewBranch(string $foodicsBranchId): bool
    {
        return !Store::where('foodics_branch_id', $foodicsBranchId)->exists();
    }
}

