<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemapCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:remap 
                            {--dry-run : Show what would be remapped without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remap products from newly created categories to existing categories by name and delete duplicates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            // Step 1: Find categories that need remapping
            // New categories have external_source='local', existing ones have external_source='foodics'
            $this->info('Finding categories to remap...');
            
            $remapData = DB::select("
                SELECT 
                    new_cat.id AS new_category_id,
                    existing_cat.id AS existing_category_id,
                    JSON_UNQUOTE(JSON_EXTRACT(new_cat.name_json, '$.en')) AS category_name,
                    COUNT(DISTINCT p.id) AS product_count
                FROM categories AS new_cat
                INNER JOIN products AS p ON p.category_id = new_cat.id
                INNER JOIN categories AS existing_cat ON 
                    LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(new_cat.name_json, '$.en')))) = 
                    LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(existing_cat.name_json, '$.en'))))
                WHERE 
                    p.external_source = 'foodics'
                    AND new_cat.external_source = 'local'
                    AND existing_cat.external_source = 'foodics'
                    AND new_cat.id != existing_cat.id
                GROUP BY new_cat.id, existing_cat.id, JSON_UNQUOTE(JSON_EXTRACT(new_cat.name_json, '$.en'))
            ");

            if (empty($remapData)) {
                $this->info('No categories found that need remapping.');
                return Command::SUCCESS;
            }

            $this->info('Found ' . count($remapData) . ' categories to remap:');
            $this->newLine();

            $totalProducts = 0;
            foreach ($remapData as $remap) {
                $this->line("  • {$remap->category_name}: {$remap->product_count} products");
                $this->line("    From category ID {$remap->new_category_id} → To category ID {$remap->existing_category_id}");
                $totalProducts += $remap->product_count;
            }

            $this->newLine();
            $this->info("Total products to remap: {$totalProducts}");

            if ($dryRun) {
                $this->newLine();
                $this->warn('Dry run complete. Run without --dry-run to apply changes.');
                return Command::SUCCESS;
            }

            // Confirm before proceeding
            if (!$this->confirm('Do you want to proceed with remapping?', true)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            DB::beginTransaction();

            try {
                // Step 2: Update products
                $this->newLine();
                $this->info('Updating products...');

                $updated = DB::update("
                    UPDATE products AS p
                    INNER JOIN categories AS new_cat ON p.category_id = new_cat.id
                    INNER JOIN categories AS existing_cat ON 
                        LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(new_cat.name_json, '$.en')))) = 
                        LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(existing_cat.name_json, '$.en'))))
                    SET p.category_id = existing_cat.id,
                        p.updated_at = NOW()
                    WHERE 
                        p.external_source = 'foodics'
                        AND new_cat.external_source = 'local'
                        AND existing_cat.external_source = 'foodics'
                        AND new_cat.id != existing_cat.id
                ");

                $this->info("Updated {$updated} products.");

                // Step 3: Delete duplicate categories (only those that now have no products)
                $this->newLine();
                $this->info('Deleting duplicate categories...');

                // Get IDs of categories that were remapped
                $remappedCategoryIds = array_column($remapData, 'new_category_id');
                
                // Delete categories that were remapped and now have no products
                $deleted = 0;
                if (!empty($remappedCategoryIds)) {
                    $deleted = DB::table('categories')
                        ->whereIn('id', $remappedCategoryIds)
                        ->whereNotExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('products')
                                ->whereColumn('products.category_id', 'categories.id');
                        })
                        ->delete();
                }

                $this->info("Deleted {$deleted} duplicate categories.");

                DB::commit();

                $this->newLine();
                $this->info('✓ Categories remapped successfully!');

                return Command::SUCCESS;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

