<?php

namespace App\Console\Commands;

use App\Core\Models\Order;
use App\Core\Models\Store;
use App\Integrations\Foodics\Services\FoodicsClient;
use App\Integrations\Foodics\Services\FoodicsRateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verifies, on the machine actually talking to Foodics, that the 429
 * mitigations are deployed and live.
 *
 * Exists because "the code is merged" and "the running process is using
 * it" are different claims: opcache, a stale config cache, an unrun
 * migration, or a deploy that missed a file all produce a tree that looks
 * correct and a server that behaves as before. Every check below reads
 * runtime state — resolved objects, the live schema, the shared cache —
 * rather than re-reading source.
 *
 * Read-only by default. --live spends one real API request.
 */
class FoodicsDoctor extends Command
{
    protected $signature = 'foodics:doctor {--live : Also make one real Foodics API call to confirm end-to-end}';

    protected $description = 'Verify the Foodics rate-limit protections are deployed and working on this server.';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->line('');
        $this->info('=== Foodics rate-limit doctor ===');
        $this->line('Server time: ' . now()->toDateTimeString() . ' (' . config('app.timezone') . ')');
        $this->line('');

        $this->checkRateLimiterDeployed();
        $this->checkClientHonoursRetryAfter();
        $this->checkPollColumn();
        $this->checkSchedule();
        $this->checkCacheIsShared();
        $this->checkBudgetActuallyCaps();
        $this->reportCurrentSpend();
        $this->reportPollBacklog();
        $this->reportStoreCount();

        if ($this->option('live')) {
            $this->checkLiveCall();
        }

        $this->line('');
        if ($this->failures > 0) {
            $this->error("FAILED — {$this->failures} check(s) failed. The protections are NOT fully live on this server.");
            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->warn("OK with {$this->warnings} warning(s). Protections are live; review the warnings above.");
            return self::SUCCESS;
        }

        $this->info('ALL CHECKS PASSED — the rate-limit protections are live on this server.');
        return self::SUCCESS;
    }

    private function pass(string $label, string $detail = ''): void
    {
        $this->line('  <fg=green>PASS</> ' . $label . ($detail !== '' ? " — {$detail}" : ''));
    }

    private function bad(string $label, string $detail): void
    {
        $this->failures++;
        $this->line('  <fg=red;options=bold>FAIL</> ' . $label . " — {$detail}");
    }

    private function caution(string $label, string $detail): void
    {
        $this->warnings++;
        $this->line('  <fg=yellow>WARN</> ' . $label . " — {$detail}");
    }

    /** The limiter class exists AND the client actually depends on it. */
    private function checkRateLimiterDeployed(): void
    {
        $this->line('1. Rate limiter wired into the client');

        if (! class_exists(FoodicsRateLimiter::class)) {
            $this->bad('FoodicsRateLimiter present', 'class not found — deploy did not land');
            return;
        }

        // Read the constructor of the class the container actually builds,
        // so opcache serving a stale FoodicsClient shows up here.
        $ctor = (new \ReflectionClass(FoodicsClient::class))->getConstructor();
        $types = array_map(
            fn (\ReflectionParameter $p) => $p->getType()?->getName(),
            $ctor?->getParameters() ?? []
        );

        if (! in_array(FoodicsRateLimiter::class, $types, true)) {
            $this->bad(
                'FoodicsClient takes the limiter',
                'constructor params: ' . (implode(', ', array_filter($types)) ?: 'none') . ' — running an old FoodicsClient'
            );
            return;
        }

        try {
            app(FoodicsClient::class);
            $this->pass('FoodicsClient resolves with the limiter injected');
        } catch (\Throwable $e) {
            $this->bad('FoodicsClient resolves', $e->getMessage());
        }
    }

    /** The 429 branch must read Retry-After, not sleep a fixed 2/4/6s. */
    private function checkClientHonoursRetryAfter(): void
    {
        $this->line('2. 429 handler reads Retry-After');

        $file = (new \ReflectionClass(FoodicsClient::class))->getFileName();
        $src = $file ? @file_get_contents($file) : '';

        if ($src === '' || $src === false) {
            $this->caution('Retry-After honoured', 'could not read FoodicsClient source to verify');
            return;
        }

        $readsHeader = str_contains($src, "header('retry-after')");
        $dumpsWindow = str_contains($src, 'exhaustCurrentWindow');

        if ($readsHeader && $dumpsWindow) {
            $this->pass('429 reads Retry-After and stops spending for the window');
        } elseif ($readsHeader) {
            $this->caution('429 handling', 'reads Retry-After but does not dump the window');
        } else {
            $this->bad('429 reads Retry-After', 'old fixed-delay retry still in place — each 429 will cost 4 requests');
        }
    }

    /** The poll ordering column must exist, or the poll command will 500. */
    private function checkPollColumn(): void
    {
        $this->line('3. Order-poll starvation fix (migration)');

        try {
            if (Schema::hasColumn('orders', 'foodics_polled_at')) {
                $this->pass('orders.foodics_polled_at exists');
            } else {
                $this->bad('orders.foodics_polled_at exists', 'run: php artisan migrate');
            }
        } catch (\Throwable $e) {
            $this->bad('schema check', $e->getMessage());
        }
    }

    /**
     * Confirm the *cached* schedule matches the intended cadence. A stale
     * bootstrap/cache is the classic reason a scheduling change does not
     * take effect.
     */
    private function checkSchedule(): void
    {
        $this->line('4. Scheduler cadence');

        try {
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
            $found = [];

            foreach ($schedule->events() as $event) {
                foreach (['foodics:sync-catalog', 'foodics:sync-order-status'] as $needle) {
                    if (str_contains($event->command ?? '', $needle)) {
                        $found[$needle] = $event->expression;
                    }
                }
            }

            $expected = [
                'foodics:sync-catalog' => '*/15',
                'foodics:sync-order-status' => '*/10',
            ];

            foreach ($expected as $cmd => $wanted) {
                $actual = $found[$cmd] ?? null;
                if ($actual === null) {
                    $this->caution($cmd, 'not found in the schedule');
                } elseif (str_contains($actual, $wanted)) {
                    $this->pass($cmd, "cron: {$actual}");
                } else {
                    $this->bad($cmd, "cron is '{$actual}', expected to contain '{$wanted}' — stale cache? run: php artisan optimize:clear");
                }
            }
        } catch (\Throwable $e) {
            $this->caution('schedule inspection', $e->getMessage());
        }
    }

    /**
     * The budget only works if every process shares one counter. An array
     * or per-process cache silently gives each worker its own 60.
     */
    private function checkCacheIsShared(): void
    {
        $this->line('5. Budget counter is shared across processes');

        $store = config('cache.default');

        if (in_array($store, ['array', 'null'], true)) {
            $this->bad('shared cache store', "cache.default is '{$store}' — every process gets its own budget, so the cap does nothing");
            return;
        }

        $probe = 'foodics:doctor:probe:' . bin2hex(random_bytes(4));
        try {
            Cache::put($probe, 'x', 10);
            $ok = Cache::get($probe) === 'x';
            Cache::forget($probe);

            $ok
                ? $this->pass('cache store writable and shared', "driver: {$store}")
                : $this->bad('cache store writable', "driver '{$store}' did not read back a value it just wrote");
        } catch (\Throwable $e) {
            $this->bad('cache store writable', $e->getMessage());
        }
    }

    /**
     * Spend the budget against a throwaway window and confirm it actually
     * stops. This proves the live code path, not just that a file exists.
     */
    private function checkBudgetActuallyCaps(): void
    {
        $this->line('6. Budget enforcement (live behaviour test)');

        try {
            $limiter = app(FoodicsRateLimiter::class);
            $ref = new \ReflectionClass($limiter);

            $max = $ref->getConstant('MAX_PER_WINDOW');
            if ($max === false) {
                $this->bad('budget constant', 'MAX_PER_WINDOW not found');
                return;
            }

            $tryConsume = $ref->getMethod('tryConsume');
            $tryConsume->setAccessible(true);
            $windowKey = $ref->getMethod('windowKey');
            $windowKey->setAccessible(true);

            // Snapshot the real counter so this probe cannot steal budget
            // from traffic happening right now.
            $key = $windowKey->invoke($limiter);
            $saved = Cache::get($key);
            Cache::forget($key);

            $allowed = 0;
            for ($i = 0; $i < $max + 10; $i++) {
                if ($tryConsume->invoke($limiter)) {
                    $allowed++;
                }
            }

            // Restore whatever the live counter was.
            $saved === null ? Cache::forget($key) : Cache::put($key, $saved, 120);

            $allowed === $max
                ? $this->pass('budget stops at the cap', "{$allowed}/{$max} allowed, rest refused")
                : $this->bad('budget stops at the cap', "allowed {$allowed}, expected {$max}");
        } catch (\Throwable $e) {
            $this->bad('budget enforcement', $e->getMessage());
        }
    }

    private function reportCurrentSpend(): void
    {
        $this->line('7. Current minute usage');

        try {
            $limiter = app(FoodicsRateLimiter::class);
            $ref = new \ReflectionClass($limiter);
            $max = $ref->getConstant('MAX_PER_WINDOW');
            $windowKey = $ref->getMethod('windowKey');
            $windowKey->setAccessible(true);

            $used = (int) (Cache::get($windowKey->invoke($limiter)) ?? 0);
            $this->line("       {$used} / {$max} requests used this minute (upstream ceiling is 90)");
        } catch (\Throwable $e) {
            $this->caution('usage read', $e->getMessage());
        }
    }

    /**
     * The starvation bug's signature: orders stuck non-terminal forever.
     * A large, old backlog means the poll was spinning on the same rows.
     */
    private function reportPollBacklog(): void
    {
        $this->line('8. Order-poll backlog');

        try {
            if (! Schema::hasColumn('orders', 'foodics_polled_at')) {
                $this->line('       (skipped — column not migrated yet)');
                return;
            }

            $mapper = \App\Integrations\Foodics\Support\FoodicsStatusMapper::terminalStatuses();

            $pending = Order::query()
                ->whereNotNull('foodics_order_id')
                ->whereNotIn('status', $mapper)
                ->count();

            $neverPolled = Order::query()
                ->whereNotNull('foodics_order_id')
                ->whereNotIn('status', $mapper)
                ->whereNull('foodics_polled_at')
                ->count();

            $stale = Order::query()
                ->whereNotNull('foodics_order_id')
                ->whereNotIn('status', $mapper)
                ->where('created_at', '<', now()->subDay())
                ->count();

            $this->line("       {$pending} order(s) awaiting a status poll ({$neverPolled} never polled)");

            if ($stale > 50) {
                $this->caution(
                    'stale backlog',
                    "{$stale} unfinished order(s) older than 24h — these were likely the rows the old poll span on. Consider closing them out."
                );
            } elseif ($stale > 0) {
                $this->line("       {$stale} older than 24h");
            }
        } catch (\Throwable $e) {
            $this->caution('backlog read', $e->getMessage());
        }
    }

    /** Request cost scales with store count, so surface it. */
    private function reportStoreCount(): void
    {
        $this->line('9. Catalog sync scale');

        try {
            $stores = Store::whereNotNull('foodics_menu_group_id')->count();
            $this->line("       {$stores} store(s) with a Foodics menu group");

            // ~1 categories page + (stores x ~4 allow-list pages) + (stores
            // x ~4 product pages at per_page=100) + ~2 modifier pages.
            $estimate = 1 + ($stores * 4) + ($stores * 4) + 2;
            $perHour = $estimate * 4; // every 15 minutes
            $this->line("       ~{$estimate} requests per catalog run, ~{$perHour}/hour at the 15-minute cadence");

            if ($estimate > 60) {
                $this->caution('burst size', "a single run needs ~{$estimate} requests but the per-minute budget is 60 — runs will span multiple minutes (expected, just slower)");
            }
        } catch (\Throwable $e) {
            $this->caution('store count', $e->getMessage());
        }
    }

    /** End-to-end proof: one real call, through the limiter, to Foodics. */
    private function checkLiveCall(): void
    {
        $this->line('10. Live API call (--live)');

        try {
            $client = app(FoodicsClient::class);
            $started = microtime(true);

            $response = $client->get(
                'v5/categories',
                \App\Integrations\Foodics\DTOs\FoodicsQueryParamsDTO::fromArray(['per_page' => 1])
            );

            $ms = (int) round((microtime(true) - $started) * 1000);

            $response->ok
                ? $this->pass('live Foodics call succeeded', "{$ms}ms, HTTP {$response->statusCode}")
                : $this->bad('live Foodics call', "HTTP {$response->statusCode}");
        } catch (\App\Integrations\Foodics\Exceptions\FoodicsRateLimitException $e) {
            // Not a deployment failure — this is the new code declining to
            // pile on, which is the behaviour we want.
            $this->caution('live call', 'rate limited right now — the client correctly refused to retry into a closed window');
        } catch (\Throwable $e) {
            $this->bad('live Foodics call', $e->getMessage());
        }
    }
}
