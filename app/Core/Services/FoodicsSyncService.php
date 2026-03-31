<?php

namespace App\Core\Services;

use App\Core\Models\Category;
use App\Core\Models\Product;
use App\Core\Models\FoodicsModifierGroup;
use App\Core\Models\FoodicsModifierOption;
use App\Integrations\Foodics\Services\FoodicsClient;
use Illuminate\Support\Facades\Log;

class FoodicsSyncService
{
    public function __construct(
        private FoodicsClient $foodicsClient
    ) {
    }

    /**
     * Sync categories from Foodics.
     *
     * @param string|null $mode 'sandbox' or 'live', null to use config default
     */
    public function syncCategories(?string $mode = null): array
    {
        $synced = 0;
        $updated = 0;
        $errors = [];

        // Default to sandbox for testing
        $mode = $mode ?? config('foodics.mode', 'sandbox');

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->foodicsClient->get('v5/categories', \App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO::fromArray([
                    'page' => $page,
                    'per_page' => 50,
                ]), $mode);

                if (!$response->ok) {
                    $errorMessage = "Failed to fetch categories page {$page}";
                    if ($response->error) {
                        $errorMessage .= ": " . ($response->error->message ?? 'Unknown error');
                    }
                    $errors[] = $errorMessage;
                    Log::error('Foodics categories sync failed', [
                        'page' => $page,
                        'status_code' => $response->status_code,
                        'error' => $response->error,
                        'response_data' => $response->data,
                    ]);
                    break;
                }

                $data = $response->data ?? [];
                $categories = $data['data'] ?? [];

                Log::info('Foodics categories sync page', [
                    'page' => $page,
                    'categories_count' => count($categories),
                    'has_data' => !empty($data),
                    'data_keys' => array_keys($data),
                ]);

                foreach ($categories as $categoryItem) {
                    try {
                        // Skip deleted/disabled items
                        if (isset($categoryItem['deleted_at']) || !($categoryItem['is_active'] ?? true)) {
                            continue;
                        }

                        $foodicsId = (string) $categoryItem['id'];
                        $existing = Category::where('foodics_id', $foodicsId)->first();

                        $categoryData = [
                            'name_json' => [
                                'en' => $categoryItem['name']['en'] ?? $categoryItem['name'] ?? '',
                                'ar' => $categoryItem['name']['ar'] ?? $categoryItem['name'] ?? '',
                            ],
                            'description_json' => [
                                'en' => $categoryItem['description']['en'] ?? $categoryItem['description'] ?? null,
                                'ar' => $categoryItem['description']['ar'] ?? $categoryItem['description'] ?? null,
                            ],
                            'image' => $categoryItem['image'] ?? null,
                            'is_active' => $categoryItem['is_active'] ?? true,
                            'external_source' => 'foodics',
                            'foodics_id' => $foodicsId,
                            'last_synced_at' => now(),
                        ];

                        if ($existing) {
                            $existing->update($categoryData);
                            $updated++;
                        } else {
                            Category::create($categoryData);
                            $synced++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error syncing category {$categoryItem['id']}: " . $e->getMessage();
                        Log::error('Foodics category sync error', [
                            'category_id' => $categoryItem['id'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Check if there are more pages
                $pagination = $response->pagination;
                if ($pagination && $pagination->meta) {
                    $currentPage = $pagination->meta->current_page ?? $page;
                    $lastPage = $pagination->meta->last_page ?? $page;
                    $hasMore = $currentPage < $lastPage;
                    $page++;
                } else {
                    // If no pagination data and no categories found, stop
                    if (empty($categories)) {
                        Log::info('Foodics categories sync: No more categories and no pagination data', [
                            'page' => $page,
                            'total_synced' => $synced,
                            'total_updated' => $updated,
                        ]);
                    }
                    $hasMore = false;
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Fatal error syncing categories: " . $e->getMessage();
            Log::error('Foodics categories sync fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return [
            'synced' => $synced,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Sync products from Foodics.
     *
     * @param string|null $mode 'sandbox' or 'live', null to use config default
     */
    public function syncProducts(?string $mode = null): array
    {
        $synced = 0;
        $updated = 0;
        $errors = [];

        // Default to sandbox for testing
        $mode = $mode ?? config('foodics.mode', 'sandbox');

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->foodicsClient->get('v5/products', \App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO::fromArray([
                    'page' => $page,
                    'per_page' => 50,
                    'include' => ['modifiers', 'modifiers.options'],
                ]), $mode);

                if (!$response->ok) {
                    $errors[] = "Failed to fetch products page {$page}";
                    break;
                }

                $data = $response->data ?? [];
                $products = $data['data'] ?? [];

                foreach ($products as $productItem) {
                    try {
                        // Skip deleted/disabled items
                        if (isset($productItem['deleted_at']) || !($productItem['is_active'] ?? true)) {
                            continue;
                        }

                        $foodicsId = (string) $productItem['id'];
                        $existing = Product::where('foodics_id', $foodicsId)->first();

                        // Find or create category
                        $categoryId = null;
                        if (isset($productItem['category_id'])) {
                            $category = Category::where('foodics_id', (string) $productItem['category_id'])->first();
                            if ($category) {
                                $categoryId = $category->id;
                            }
                        }

                        // If no category found, skip or use default
                        if (!$categoryId) {
                            $errors[] = "Product {$foodicsId} has no valid category";
                            continue;
                        }

                        $productData = [
                            'category_id' => $categoryId,
                            'name_json' => [
                                'en' => $productItem['name']['en'] ?? $productItem['name'] ?? '',
                                'ar' => $productItem['name']['ar'] ?? $productItem['name'] ?? '',
                            ],
                            'description_json' => [
                                'en' => $productItem['description']['en'] ?? $productItem['description'] ?? null,
                                'ar' => $productItem['description']['ar'] ?? $productItem['description'] ?? null,
                            ],
                            'image' => $productItem['image'] ?? null,
                            'base_price' => $productItem['price'] ?? $productItem['base_price'] ?? 0,
                            'is_active' => $productItem['is_active'] ?? true,
                            'external_source' => 'foodics',
                            'foodics_id' => $foodicsId,
                            'last_synced_at' => now(),
                        ];

                        if ($existing) {
                            $existing->update($productData);
                            $updated++;
                            $savedProduct = $existing;
                        } else {
                            $savedProduct = Product::create($productData);
                            $synced++;
                        }

                        // Sync Foodics modifier groups attached to this product
                        if (!empty($productItem['modifiers'])) {
                            $this->syncProductModifierGroups($savedProduct, $productItem['modifiers']);
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error syncing product {$productItem['id']}: " . $e->getMessage();
                        Log::error('Foodics product sync error', [
                            'product_id' => $productItem['id'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Check if there are more pages
                $pagination = $response->pagination;
                if ($pagination && $pagination->meta) {
                    $currentPage = $pagination->meta->current_page ?? $page;
                    $lastPage = $pagination->meta->last_page ?? $page;
                    $hasMore = $currentPage < $lastPage;
                    $page++;
                } else {
                    $hasMore = false;
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Fatal error syncing products: " . $e->getMessage();
            Log::error('Foodics products sync fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return [
            'synced' => $synced,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Sync all Foodics modifiers (option groups) and their options.
     * This can be called independently to pre-populate modifier data.
     *
     * @param string|null $mode 'sandbox' or 'live', null to use config default
     */
    public function syncModifiers(?string $mode = null): array
    {
        $synced = 0;
        $updated = 0;
        $errors = [];

        $mode = $mode ?? config('foodics.mode', 'sandbox');

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->foodicsClient->get('v5/modifiers', \App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO::fromArray([
                    'page' => $page,
                    'per_page' => 50,
                    'include' => ['options'],
                ]), $mode);

                if (!$response->ok) {
                    $errors[] = "Failed to fetch modifiers page {$page}";
                    break;
                }

                $data = $response->data ?? [];
                $modifiers = $data['data'] ?? [];

                foreach ($modifiers as $modifierItem) {
                    try {
                        $foodicsId = (string) $modifierItem['id'];

                        $groupData = [
                            'foodics_id'      => $foodicsId,
                            'name_json'       => [
                                'en' => $modifierItem['name'] ?? '',
                                'ar' => $modifierItem['name_localized'] ?? $modifierItem['name'] ?? '',
                            ],
                            'last_synced_at'  => now(),
                        ];

                        $group = FoodicsModifierGroup::updateOrCreate(
                            ['foodics_id' => $foodicsId],
                            $groupData
                        );

                        $group->wasRecentlyCreated ? $synced++ : $updated++;

                        // Sync the options for this modifier group
                        foreach ($modifierItem['options'] ?? [] as $optionItem) {
                            $this->upsertModifierOption($group->id, $optionItem);
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error syncing modifier {$modifierItem['id']}: " . $e->getMessage();
                        Log::error('Foodics modifier sync error', [
                            'modifier_id' => $modifierItem['id'] ?? null,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }

                $pagination = $response->pagination;
                if ($pagination && $pagination->meta) {
                    $currentPage = $pagination->meta->current_page ?? $page;
                    $lastPage    = $pagination->meta->last_page ?? $page;
                    $hasMore     = $currentPage < $lastPage;
                    $page++;
                } else {
                    $hasMore = false;
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Fatal error syncing modifiers: " . $e->getMessage();
            Log::error('Foodics modifiers sync fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return [
            'synced'  => $synced,
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }

    /**
     * Sync the modifier groups attached to a single product,
     * including upserting modifier group records, their options,
     * and the product–modifier-group pivot rows.
     */
    private function syncProductModifierGroups(Product $product, array $modifiers): void
    {
        $syncedGroupIds = [];

        foreach ($modifiers as $modifierItem) {
            try {
                $foodicsId = (string) $modifierItem['id'];

                $group = FoodicsModifierGroup::updateOrCreate(
                    ['foodics_id' => $foodicsId],
                    [
                        'name_json'      => [
                            'en' => $modifierItem['name'] ?? '',
                            'ar' => $modifierItem['name_localized'] ?? $modifierItem['name'] ?? '',
                        ],
                        'last_synced_at' => now(),
                    ]
                );

                // Sync options nested within this modifier
                foreach ($modifierItem['options'] ?? [] as $optionItem) {
                    $this->upsertModifierOption($group->id, $optionItem);
                }

                // Determine pivot values from the modifier's pivot data
                $pivot = $modifierItem['pivot'] ?? [];

                $product->foodicsModifierGroups()->syncWithoutDetaching([
                    $group->id => [
                        'minimum_options' => $pivot['minimum_options'] ?? null,
                        'maximum_options' => $pivot['maximum_options'] ?? null,
                        'free_options'    => $pivot['free_options'] ?? null,
                        'index'           => $pivot['index'] ?? null,
                    ],
                ]);

                $syncedGroupIds[] = $group->id;
            } catch (\Exception $e) {
                Log::error('Foodics product modifier group sync error', [
                    'product_id'  => $product->id,
                    'modifier_id' => $modifierItem['id'] ?? null,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // Detach modifier groups no longer returned by the API for this product
        $product->foodicsModifierGroups()->detach(
            $product->foodicsModifierGroups()->pluck('foodics_modifier_groups.id')->diff($syncedGroupIds)->values()
        );
    }

    /**
     * Upsert a single Foodics modifier option record.
     */
    private function upsertModifierOption(int $groupId, array $optionItem): void
    {
        FoodicsModifierOption::updateOrCreate(
            ['foodics_id' => (string) $optionItem['id']],
            [
                'foodics_modifier_group_id' => $groupId,
                'name_json'                 => [
                    'en' => $optionItem['name'] ?? '',
                    'ar' => $optionItem['name_localized'] ?? $optionItem['name'] ?? '',
                ],
                'price'                     => $optionItem['price'] ?? 0,
                'is_active'                 => $optionItem['is_active'] ?? true,
                'last_synced_at'            => now(),
            ]
        );
    }
}

