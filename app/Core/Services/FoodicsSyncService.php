<?php

namespace App\Core\Services;

use App\Core\Models\Category;
use App\Core\Models\Product;
use App\Core\Models\FoodicsModifier;
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

        // Categories must exist before products can be linked — sync them first
        $categoryResult = $this->syncCategories($mode);
        if (!empty($categoryResult['errors'])) {
            Log::warning('Foodics category pre-sync had errors', ['errors' => $categoryResult['errors']]);
        }
        Log::info('Foodics category pre-sync completed', [
            'synced' => $categoryResult['synced'],
            'updated' => $categoryResult['updated'],
        ]);

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->foodicsClient->get('v5/products', \App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO::fromArray([
                    'page' => $page,
                    'per_page' => 50,
                    'include' => ['category', 'modifiers', 'modifiers.options'],
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

                        // Find or create category — API returns a nested 'category' object when included
                        $categoryId = null;
                        $foodicsCategoryId = $productItem['category']['id'] ?? $productItem['category_id'] ?? null;
                        if ($foodicsCategoryId) {
                            $category = Category::where('foodics_id', (string) $foodicsCategoryId)->first();
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

                        // Sync Foodics modifiers (option groups + options) attached to this product
                        if (!empty($productItem['modifiers'])) {
                            $this->syncProductModifiers($savedProduct, $productItem['modifiers']);
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
     * Sync all Foodics modifiers and their options independently.
     * Use this to pre-populate modifier data before syncing products.
     *
     * Foodics terminology:
     *   modifier       → the group container  (e.g. "Milk Type", "Size")
     *   modifier_option → the selectable item (e.g. "Oat Milk +2.50")
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
                    'page'    => $page,
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
                        $modifier = FoodicsModifier::updateOrCreate(
                            ['foodics_id' => (string) $modifierItem['id']],
                            [
                                'name_json'      => [
                                    'en' => $modifierItem['name'] ?? '',
                                    'ar' => $modifierItem['name_localized'] ?? $modifierItem['name'] ?? '',
                                ],
                                'last_synced_at' => now(),
                            ]
                        );

                        $modifier->wasRecentlyCreated ? $synced++ : $updated++;

                        foreach ($modifierItem['options'] ?? [] as $optionItem) {
                            $this->upsertModifierOption($modifier->id, $optionItem);
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
     * Upsert the Foodics modifiers attached to a product.
     * Each modifier item includes nested options and pivot fields.
     */
    private function syncProductModifiers(Product $product, array $modifiers): void
    {
        $syncedModifierIds = [];

        foreach ($modifiers as $modifierItem) {
            try {
                $modifier = FoodicsModifier::updateOrCreate(
                    ['foodics_id' => (string) $modifierItem['id']],
                    [
                        'name_json'      => [
                            'en' => $modifierItem['name'] ?? '',
                            'ar' => $modifierItem['name_localized'] ?? $modifierItem['name'] ?? '',
                        ],
                        'last_synced_at' => now(),
                    ]
                );

                foreach ($modifierItem['options'] ?? [] as $optionItem) {
                    $this->upsertModifierOption($modifier->id, $optionItem);
                }

                // Foodics returns pivot fields directly on the modifier item
                // when the include is via a product relationship.
                $pivot = $modifierItem['pivot'] ?? $modifierItem;

                $product->foodicsModifiers()->syncWithoutDetaching([
                    $modifier->id => [
                        'minimum_options'     => $pivot['minimum_options'] ?? null,
                        'maximum_options'     => $pivot['maximum_options'] ?? null,
                        'free_options'        => $pivot['free_options'] ?? null,
                        'default_option_ids'  => isset($pivot['default_options_ids'])
                            ? json_encode($pivot['default_options_ids'])
                            : null,
                        'excluded_option_ids' => isset($pivot['excluded_options_ids'])
                            ? json_encode($pivot['excluded_options_ids'])
                            : null,
                        'unique_options'         => (bool) ($pivot['unique_options'] ?? false),
                        'is_splittable_in_half'  => (bool) ($pivot['is_splittable_in_half'] ?? false),
                        'sort_order'             => $pivot['index'] ?? null,
                    ],
                ]);

                $syncedModifierIds[] = $modifier->id;
            } catch (\Exception $e) {
                Log::error('Foodics product modifier sync error', [
                    'product_id'  => $product->id,
                    'modifier_id' => $modifierItem['id'] ?? null,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // Detach modifiers that Foodics no longer returns for this product
        $existingIds = $product->foodicsModifiers()
            ->pluck('foodics_modifiers.id')
            ->diff($syncedModifierIds)
            ->values();

        if ($existingIds->isNotEmpty()) {
            $product->foodicsModifiers()->detach($existingIds);
        }
    }

    /**
     * Upsert a single Foodics modifier option.
     * Stores name, price, sku, calories, sort_order and is_active.
     */
    private function upsertModifierOption(int $modifierId, array $optionItem): void
    {
        FoodicsModifierOption::updateOrCreate(
            ['foodics_id' => (string) $optionItem['id']],
            [
                'foodics_modifier_id' => $modifierId,
                'name_json'           => [
                    'en' => $optionItem['name'] ?? '',
                    'ar' => $optionItem['name_localized'] ?? $optionItem['name'] ?? '',
                ],
                'price'          => $optionItem['price'] ?? 0,
                'sku'            => $optionItem['sku'] ?? null,
                'calories'       => $optionItem['calories'] ?? null,
                'sort_order'     => $optionItem['index'] ?? null,
                'is_active'      => $optionItem['is_active'] ?? true,
                'last_synced_at' => now(),
            ]
        );
    }
}

