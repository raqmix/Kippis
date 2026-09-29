<?php

namespace Database\Seeders;

use App\Core\Models\Store;
use Illuminate\Database\Seeder;

/**
 * Sets opening hours on the live branches and deactivates the ones that are
 * not currently taking orders.
 *
 * Unlike StoreSeeder, this creates nothing. It matches branches that already
 * exist by name and updates them, so it is safe to run against production and
 * safe to run more than once. A branch it cannot find is reported and skipped,
 * never created — a typo here should surface as a warning, not as a duplicate
 * branch competing with the real one.
 *
 * Hours requested 2026-09-29:
 *   Ignite Almaza    08:00 – 22:00
 *   Ignite Melanite  08:00 – 22:00
 *   every other active branch  09:00 – 23:00
 *
 * Deactivated because they are not currently available: D5 / District 5,
 * Sahel, Events, U Venues.
 *
 * IMPORTANT — read before trusting the result. `Store::isOpenNow()` compares
 * these times against `now()`, which runs in `config('app.timezone')`. That
 * defaults to UTC, so on a UTC server these hours are evaluated two to three
 * hours off Egypt local time and a branch will read as closed during its
 * first hours of business. Setting correct hours does not fix that on its
 * own. This seeder warns when it detects the mismatch.
 *
 * Run with:
 *   php artisan db:seed --class=BranchHoursSeeder
 */
class BranchHoursSeeder extends Seeder
{
    private const EARLY_OPEN = '08:00';
    private const EARLY_CLOSE = '22:00';

    private const STANDARD_OPEN = '09:00';
    private const STANDARD_CLOSE = '23:00';

    /**
     * Branches on the earlier schedule, matched case-insensitively against
     * the branch name.
     */
    private const EARLY_BRANCHES = [
        'Ignite Almaza',
        'Ignite Melanite',
    ];

    /**
     * Branches to take out of the picker. Several spellings per branch
     * because the portal and Foodics do not always agree on the name.
     */
    private const UNAVAILABLE_BRANCHES = [
        'D5',
        'District 5',
        'Sahel',
        'Events',
        'U Venues',
    ];

    public function run(): void
    {
        $this->warnAboutTimezone();

        $stores = Store::query()->withTrashed()->get();

        if ($stores->isEmpty()) {
            $this->command?->error('No branches found. Nothing to update.');
            return;
        }

        $deactivated = $this->deactivateUnavailable($stores);
        $early = $this->applyEarlyHours($stores);
        $standard = $this->applyStandardHours($stores, $deactivated, $early);

        $this->report($stores, $deactivated, $early, $standard);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Store>  $stores
     * @return array<int, int>  ids of branches deactivated
     */
    private function deactivateUnavailable($stores): array
    {
        $ids = [];

        foreach ($stores as $store) {
            if (! $this->nameMatchesAny($store->name, self::UNAVAILABLE_BRANCHES)) {
                continue;
            }

            $ids[] = $store->id;

            if (! $store->is_active && ! $store->receive_online_orders) {
                $this->command?->line("  already inactive: {$store->name}");
                continue;
            }

            $store->update([
                'is_active' => false,
                'receive_online_orders' => false,
            ]);

            $this->command?->line("  <fg=yellow>deactivated</>: {$store->name}");
        }

        return $ids;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Store>  $stores
     * @return array<int, int>  ids given the early schedule
     */
    private function applyEarlyHours($stores): array
    {
        $ids = [];

        foreach (self::EARLY_BRANCHES as $wanted) {
            $matches = $stores->filter(
                fn (Store $store) => $this->nameMatches($store->name, $wanted)
            );

            if ($matches->isEmpty()) {
                // Deliberately not created. Ignite Melanite in particular is
                // reported as missing from the portal; adding it here would
                // produce a branch with no Foodics mapping that customers
                // could order from.
                $this->command?->warn("  NOT FOUND: {$wanted} — add it in the portal, then re-run.");
                continue;
            }

            if ($matches->count() > 1) {
                $this->command?->warn(
                    "  {$wanted} matches " . $matches->count() . ' branches (ids: '
                    . $matches->pluck('id')->implode(', ') . ') — check for duplicates.'
                );
            }

            foreach ($matches as $store) {
                $ids[] = $store->id;
                $this->setHours($store, self::EARLY_OPEN, self::EARLY_CLOSE);
            }
        }

        return $ids;
    }

    /**
     * Everything still active that is not on the early schedule.
     *
     * @param  \Illuminate\Support\Collection<int, Store>  $stores
     * @param  array<int, int>  $deactivated
     * @param  array<int, int>  $early
     * @return array<int, int>
     */
    private function applyStandardHours($stores, array $deactivated, array $early): array
    {
        $handled = array_merge($deactivated, $early);
        $ids = [];

        foreach ($stores as $store) {
            if (in_array($store->id, $handled, true)) {
                continue;
            }

            // Leave already-inactive branches alone: they were turned off
            // for a reason this seeder does not know about.
            if (! $store->is_active) {
                $this->command?->line("  skipped (inactive): {$store->name}");
                continue;
            }

            $ids[] = $store->id;
            $this->setHours($store, self::STANDARD_OPEN, self::STANDARD_CLOSE);
        }

        return $ids;
    }

    private function setHours(Store $store, string $open, string $close): void
    {
        $before = $this->formatHours($store);

        $store->update([
            'open_time' => $open,
            'close_time' => $close,
        ]);

        $this->command?->line("  <fg=green>{$store->name}</>: {$before} → {$open}–{$close}");
    }

    private function formatHours(Store $store): string
    {
        if (! $store->open_time || ! $store->close_time) {
            // Worth calling out: a branch with no hours is treated as always
            // open by isOpenNow(), so it also bypasses the closed-branch
            // check at checkout.
            return '(none — was always open)';
        }

        return substr((string) $store->open_time, 0, 5) . '–' . substr((string) $store->close_time, 0, 5);
    }

    private function nameMatches(?string $name, string $wanted): bool
    {
        if (! $name) {
            return false;
        }

        return str_contains(
            $this->normalise($name),
            $this->normalise($wanted)
        );
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function nameMatchesAny(?string $name, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->nameMatches($name, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fold case and strip punctuation so "Park St." and "park st" match, and
     * so "D5" matches "District 5 (D5)".
     */
    private function normalise(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($value)) ?? '';
    }

    private function warnAboutTimezone(): void
    {
        $tz = config('app.timezone');

        if ($tz === 'Africa/Cairo') {
            return;
        }

        $this->command?->warn('');
        $this->command?->warn("  app.timezone is '{$tz}', not Africa/Cairo.");
        $this->command?->warn('  Store::isOpenNow() compares these hours against that timezone, so');
        $this->command?->warn('  is_open_now will be wrong by 2-3 hours until that is resolved.');
        $this->command?->warn('  The hours below will be stored correctly regardless.');
        $this->command?->warn('');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Store>  $stores
     * @param  array<int, int>  $deactivated
     * @param  array<int, int>  $early
     * @param  array<int, int>  $standard
     */
    private function report($stores, array $deactivated, array $early, array $standard): void
    {
        $this->command?->info('');
        $this->command?->info('Branch hours updated:');
        $this->command?->line('  ' . count($early) . " branch(es) set to " . self::EARLY_OPEN . '–' . self::EARLY_CLOSE);
        $this->command?->line('  ' . count($standard) . " branch(es) set to " . self::STANDARD_OPEN . '–' . self::STANDARD_CLOSE);
        $this->command?->line('  ' . count($deactivated) . ' branch(es) deactivated');

        $stillMissing = $stores
            ->filter(fn (Store $s) => $s->is_active && (! $s->open_time || ! $s->close_time))
            ->pluck('name');

        if ($stillMissing->isNotEmpty()) {
            $this->command?->warn('');
            $this->command?->warn('  Active branches still without hours (they will read as always open):');
            foreach ($stillMissing as $name) {
                $this->command?->warn("    - {$name}");
            }
        }
    }
}
