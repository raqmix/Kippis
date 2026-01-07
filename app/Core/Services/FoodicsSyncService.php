<?php

namespace App\Core\Services;

use App\Core\Models\Category;
use App\Core\Models\Product;
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
     */
    public function syncCategories(): array
    {
        $synced = 0;
        $updated = 0;
        $errors = [];

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->foodicsClient->get('v5/categories', \App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO::fromArray([
                    'page' => $page,
                    'per_page' => 50,
                ]));

                if (!$response->ok) {
                    $errors[] = "Failed to fetch categories page {$page}";
                    break;
                }

                $data = $response->data ?? [];
                $categories = $data['data'] ?? [];

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
     */
    public function syncProducts(): array
    {
        $synced = 0;
        $updated = 0;
        $errors = [];

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->foodicsClient->get('v5/products', \App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO::fromArray([
                    'page' => $page,
                    'per_page' => 50,
                ]));

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
                        } else {
                            Product::create($productData);
                            $synced++;
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
}

