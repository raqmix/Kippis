<?php

namespace App\Console\Commands;

use App\Core\Services\FoodicsSyncService;
use App\Support\Heartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pull the Foodics catalog into Kippis. Called from the scheduler every
 * 5 minutes (see routes/console.php) and manually from the Filament dash
 * or CLI when an operator needs an on-demand refresh.
 *
 * What this run does (via FoodicsSyncService):
 *   1. syncAllStoreMenus() — per configured store, pull that branch's menu
 *      group. Internally that also (re)runs syncCategories, honors local
 *      admin overrides, soft-deletes products Foodics turned off, sweeps
 *      orphans (products no group still surfaces), and empties out
 *      categories that end up with no active products.
 *   2. syncModifiers() — refresh option groups + option prices/labels.
 *
 * Discrepancy budget: after this command returns, the app's catalog is a
 * mirror of what Foodics currently returns for each store's menu group,
 * except for fields an admin has explicitly pinned via
 * Product::locally_overridden_fields (image, translations, etc.). That
 * pin is intentional — see FoodicsSyncService::syncProducts.
 *
 * Concurrency: guarded by a Cache lock so two overlapping runs (scheduler
 * misfire, manual run alongside scheduler) can't stomp each other. Lock
 * TTL is bounded so a crashed run frees the slot within ~10 minutes.
 */
class FoodicsSyncCatalog extends Command
{
    protected $signature = 'foodics:sync-catalog
        {--skip-modifiers : Skip the modifiers sync (categories + products only)}
        {--mode= : Override Foodics environment (sandbox|live). Defaults to config.}';

    protected $description = 'Sync Foodics categories, per-branch products, and modifiers into the local catalog.';

    /**
     * Max time we let a single run hold the lock. A run now spaces its
     * requests behind the shared Foodics rate limiter, so it can spend
     * minutes waiting on the per-minute budget rather than the 30-90s it
     * took when it fired everything at once. Still short enough that a
     * crashed run frees the slot before it blocks two scheduler ticks.
     */
    private const LOCK_TTL_SECONDS = 1200;

    public function handle(FoodicsSyncService $sync): int
    {
        $mode = $this->option('mode') ?: null;
        $skipModifiers = (bool) $this->option('skip-modifiers');

        $lock = Cache::lock('foodics:sync-catalog', self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another foodics:sync-catalog run holds the lock — skipping this tick.');
            Log::info('FOODICS_CATALOG_SYNC_SKIPPED_LOCKED');
            // Not a failure — the previous run will finish and the next
            // scheduler tick picks up any drift.
            return self::SUCCESS;
        }

        $startedAt = microtime(true);

        try {
            $menuResult = $sync->syncAllStoreMenus($mode);

            $modifierResult = ['synced' => 0, 'updated' => 0, 'errors' => []];
            if (! $skipModifiers) {
                $modifierResult = $sync->syncModifiers($mode);
            }

            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            $summary = [
                'elapsed_ms' => $elapsedMs,
                'mode' => $mode ?? config('foodics.mode', 'sandbox'),
                'menus' => [
                    'synced' => $menuResult['synced'] ?? 0,
                    'updated' => $menuResult['updated'] ?? 0,
                    'removed_inactive' => $menuResult['removed_inactive'] ?? 0,
                    'removed_orphans' => $menuResult['removed_orphans'] ?? 0,
                    'emptied_categories' => $menuResult['emptied_categories'] ?? 0,
                    'errors' => $menuResult['errors'] ?? [],
                ],
                'modifiers' => [
                    'synced' => $modifierResult['synced'] ?? 0,
                    'updated' => $modifierResult['updated'] ?? 0,
                    'errors' => $modifierResult['errors'] ?? [],
                    'skipped' => $skipModifiers,
                ],
            ];

            Log::info('FOODICS_CATALOG_SYNC_RUN', $summary);
            Heartbeat::mark('foodics:sync-catalog');

            $this->info(sprintf(
                'Sync OK in %dms — products +%d ~%d -%d (inactive %d, orphans %d, empty cats %d); modifiers +%d ~%d%s',
                $elapsedMs,
                $summary['menus']['synced'],
                $summary['menus']['updated'],
                $summary['menus']['removed_inactive'] + $summary['menus']['removed_orphans'],
                $summary['menus']['removed_inactive'],
                $summary['menus']['removed_orphans'],
                $summary['menus']['emptied_categories'],
                $summary['modifiers']['synced'],
                $summary['modifiers']['updated'],
                $skipModifiers ? ' (modifiers skipped)' : ''
            ));

            $errorCount = count($summary['menus']['errors']) + count($summary['modifiers']['errors']);
            if ($errorCount > 0) {
                foreach ($summary['menus']['errors'] as $err) {
                    $this->warn("menu: {$err}");
                }
                foreach ($summary['modifiers']['errors'] as $err) {
                    $this->warn("modifier: {$err}");
                }
                // Non-zero exit so the scheduler surfaces the failure in
                // the daily health digest, but we still mark the
                // heartbeat above — this was a partial success, not a
                // dead sync.
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('FOODICS_CATALOG_SYNC_FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
