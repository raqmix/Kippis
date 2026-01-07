<?php

namespace App\Console\Commands;

use App\Core\Models\Category;
use App\Core\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportProductsFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:import-csv 
                            {file=products-import.csv : Path to the CSV file}
                            {--dry-run : Run without actually importing data}
                            {--update : Update existing products based on foodics_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from CSV file with category mapping via category_reference';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');
        $update = $this->option('update');

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Reading CSV file: {$filePath}");
        
        // Read CSV file
        $rows = $this->readCsv($filePath);
        
        if (empty($rows)) {
            $this->error("No data found in CSV file");
            return Command::FAILURE;
        }

        $this->info("Found " . count($rows) . " products to import");
        $this->newLine();

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No data will be imported");
            $this->newLine();
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'categories_created' => 0,
        ];

        $categoryCache = [];

        // Process each row
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                try {
                    // Get or create category
                    $categoryReference = $row['category_reference'] ?? null;
                    
                    if (empty($categoryReference)) {
                        $this->newLine();
                        $this->warn("Row " . ($index + 2) . ": Skipping - no category_reference");
                        $stats['skipped']++;
                        $bar->advance();
                        continue;
                    }

                    // Get or create category
                    if (!isset($categoryCache[$categoryReference])) {
                        $category = $this->getOrCreateCategory($categoryReference, $dryRun);
                        $categoryCache[$categoryReference] = $category;
                        
                        if ($category && !$dryRun) {
                            $stats['categories_created']++;
                        }
                    } else {
                        $category = $categoryCache[$categoryReference];
                    }

                    if (!$category) {
                        $this->newLine();
                        $this->error("Row " . ($index + 2) . ": Failed to create category: {$categoryReference}");
                        $stats['errors']++;
                        $bar->advance();
                        continue;
                    }

                    // Prepare product data
                    $productData = $this->prepareProductData($row, $category->id);

                    // Check if product exists
                    $foodicsId = $row['id'] ?? null;
                    $existingProduct = null;
                    
                    if ($foodicsId) {
                        $existingProduct = Product::where('foodics_id', $foodicsId)->first();
                    }

                    if ($existingProduct) {
                        if ($update && !$dryRun) {
                            $existingProduct->update($productData);
                            $stats['updated']++;
                        } else {
                            $stats['skipped']++;
                        }
                    } else {
                        if (!$dryRun) {
                            Product::create($productData);
                            $stats['created']++;
                        } else {
                            $stats['created']++;
                        }
                    }

                    $bar->advance();
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Row " . ($index + 2) . ": Error - " . $e->getMessage());
                    $stats['errors']++;
                    $bar->advance();
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            $bar->finish();
            $this->newLine(2);

            // Display summary
            $this->displaySummary($stats, $dryRun);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine();
            $this->error("Fatal error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Read CSV file and return array of rows.
     */
    private function readCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            return [];
        }

        // Read header row
        $headers = fgetcsv($handle);
        
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        // Normalize headers (trim and lowercase)
        $headers = array_map(function ($header) {
            return trim(strtolower($header));
        }, $headers);

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue; // Skip malformed rows
            }

            $rows[] = array_combine($headers, $row);
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Get or create category based on reference.
     */
    private function getOrCreateCategory(string $categoryReference, bool $dryRun): ?Category
    {
        // Try to find by name (case-insensitive match in English)
        $categoryName = $this->formatCategoryName($categoryReference);
        
        // Search for category by English name
        // Load all local categories and filter in memory for case-insensitive matching
        // This is more reliable across different database systems
        $categories = Category::where('external_source', 'local')->get();
        
        $category = $categories->first(function ($cat) use ($categoryName, $categoryReference) {
            $nameEn = $cat->name_json['en'] ?? '';
            $nameEnLower = strtolower(trim($nameEn));
            $categoryNameLower = strtolower(trim($categoryName));
            $referenceLower = strtolower(trim($categoryReference));
            
            // Exact match
            if ($nameEnLower === $categoryNameLower) {
                return true;
            }
            
            // Match with reference (handles variations)
            if ($nameEnLower === $referenceLower) {
                return true;
            }
            
            // Partial match (handles cases where name might include the reference)
            if (strpos($nameEnLower, $referenceLower) !== false || 
                strpos($nameEnLower, str_replace(['-', '_'], ' ', $referenceLower)) !== false) {
                return true;
            }
            
            return false;
        });

        if ($category) {
            return $category;
        }

        // Create new category if not found
        if ($dryRun) {
            return new Category([
                'name_json' => [
                    'en' => $categoryName,
                    'ar' => $categoryName, // Fallback to English if no Arabic translation
                ],
                'is_active' => true,
                'external_source' => 'local',
            ]);
        }

        return Category::create([
            'name_json' => [
                'en' => $categoryName,
                'ar' => $categoryName, // Fallback to English if no Arabic translation
            ],
            'description_json' => null,
            'is_active' => true,
            'external_source' => 'local',
        ]);
    }

    /**
     * Format category reference to a proper name.
     */
    private function formatCategoryName(string $reference): string
    {
        // Convert kebab-case or snake_case to Title Case
        return Str::title(str_replace(['-', '_'], ' ', $reference));
    }

    /**
     * Prepare product data from CSV row.
     */
    private function prepareProductData(array $row, int $categoryId): array
    {
        // Prepare name_json
        $nameEn = $row['name'] ?? '';
        $nameAr = !empty($row['name_localized']) ? $row['name_localized'] : $nameEn;
        
        $nameJson = [
            'en' => $nameEn,
            'ar' => $nameAr,
        ];

        // Prepare description_json
        $descriptionEn = $row['description'] ?? null;
        $descriptionAr = !empty($row['description_localized']) ? $row['description_localized'] : $descriptionEn;
        
        $descriptionJson = null;
        if ($descriptionEn || $descriptionAr) {
            $descriptionJson = [
                'en' => $descriptionEn ?? '',
                'ar' => $descriptionAr ?? '',
            ];
        }

        // Convert is_active (Yes/No) to boolean
        $isActive = strtolower(trim($row['is_active'] ?? 'Yes')) === 'yes';

        // Convert price to decimal
        $basePrice = !empty($row['price']) ? (float) $row['price'] : 0.0;

        // Get image URL
        $image = !empty($row['image']) ? trim($row['image']) : null;

        // Get foodics_id
        $foodicsId = !empty($row['id']) ? trim($row['id']) : null;

        return [
            'category_id' => $categoryId,
            'name_json' => $nameJson,
            'description_json' => $descriptionJson,
            'image' => $image,
            'base_price' => $basePrice,
            'is_active' => $isActive,
            'external_source' => 'foodics',
            'foodics_id' => $foodicsId,
            'last_synced_at' => now(),
            'product_kind' => 'regular',
        ];
    }

    /**
     * Display import summary.
     */
    private function displaySummary(array $stats, bool $dryRun): void
    {
        $this->info('=' . str_repeat('=', 60));
        $this->info('Import Summary' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->info('=' . str_repeat('=', 60));
        $this->info("Products created: {$stats['created']}");
        $this->info("Products updated: {$stats['updated']}");
        $this->info("Products skipped: {$stats['skipped']}");
        $this->info("Errors: {$stats['errors']}");
        $this->info("Categories created: {$stats['categories_created']}");
        $this->info('=' . str_repeat('=', 60));
    }
}

