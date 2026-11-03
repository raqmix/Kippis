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

        // Ignore branches that don't receive online orders. Foodics v5 names
        // this flag `receives_online_orders`; the old `receive_online_orders`
        // key never exists in its payload, so this filter used to be a no-op.
        $receivesOnline = $branchData['receives_online_orders']
            ?? $branchData['receive_online_orders']
            ?? null;
        if ($receivesOnline !== null && ! $receivesOnline) {
            throw new \Exception('Branch does not receive online orders');
        }

        $foodicsBranchId = $branchData['id'] ?? null;

        if (!$foodicsBranchId) {
            throw new \Exception('Branch ID is missing');
        }

        $store = Store::where('foodics_branch_id', $foodicsBranchId)->first();

        // Only fields Foodics actually has a value for. A null/blank from
        // Foodics must not wipe what an admin set in Kippis (location,
        // hours, Arabic name), since most Foodics branches carry none.
        $fromFoodics = array_filter([
            'name' => $this->blankToNull($branchData['name'] ?? null),
            'address' => $this->blankToNull($branchData['address'] ?? null),
            'latitude' => is_numeric($branchData['latitude'] ?? null) ? (float) $branchData['latitude'] : null,
            'longitude' => is_numeric($branchData['longitude'] ?? null) ? (float) $branchData['longitude'] : null,
            'phone' => $this->blankToNull($branchData['phone'] ?? null),
        ], fn ($v) => $v !== null);

        // Foodics sends opening_from/opening_to; equal values (00:00-00:00 is
        // its default) mean "not configured", so leave Kippis hours alone.
        $from = $this->blankToNull($branchData['opening_from'] ?? null);
        $to = $this->blankToNull($branchData['opening_to'] ?? null);
        if ($from && $to && $from !== $to) {
            $fromFoodics['open_time'] = $from;
            $fromFoodics['close_time'] = $to;
        }

        $arabic = $this->blankToNull($branchData['name_localized'] ?? null);
        if ($arabic !== null) {
            $existing = $store?->name_localized ?? [];
            $fromFoodics['name_localized'] = array_merge(
                is_array($existing) ? $existing : [],
                ['en' => $fromFoodics['name'] ?? $store?->name, 'ar' => $arabic]
            );
        }

        $fromFoodics['synced_from_foodics_at'] = now();

        if ($store) {
            // Visibility (is_active, receive_online_orders, employee/proximity
            // flags) is owned by the Kippis admin and never touched by a sync:
            // Foodics has no is_active on branches, and the old `?? true`
            // default silently switched hidden branches back on.
            $store->update($fromFoodics);

            return $store;
        }

        // New branches arrive hidden. Going live needs a menu group and a
        // deliberate switch-on in the admin, or customers see an empty branch.
        return Store::create($fromFoodics + [
            'name' => $branchData['reference'] ?? $foodicsBranchId,
            'foodics_branch_id' => $foodicsBranchId,
            'is_active' => false,
            'receive_online_orders' => false,
        ]);
    }

    private function blankToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return ($value === '' || $value === null) ? null : $value;
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

